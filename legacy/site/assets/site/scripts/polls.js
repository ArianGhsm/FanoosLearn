(function () {
  "use strict";

  if (!window.Dent1402Auth) {
    return;
  }

  function $(id) {
    return document.getElementById(id);
  }

  function safeAuthApi() {
    return window.Dent1402Auth && typeof window.Dent1402Auth === "object"
      ? window.Dent1402Auth
      : null;
  }

  function nowPath() {
    return window.location.pathname + window.location.search + window.location.hash;
  }

  function toIsoValue(inputValue) {
    var value = String(inputValue || "").trim();
    if (!value) return "";
    var dt = new Date(value);
    if (Number.isNaN(dt.getTime())) return "";
    return dt.toISOString();
  }

  function formatDateTime(ts) {
    if (!ts) return "—";
    try {
      return new Date(ts * 1000).toLocaleString("fa-IR-u-ca-persian", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        hour12: false
      });
    } catch (_error) {
      return "—";
    }
  }

  function statusLabel(status) {
    if (status === "open") return "فعال";
    if (status === "closed") return "بسته";
    if (status === "scheduled") return "زمان‌بندی‌شده";
    return "نامشخص";
  }

  function statusClass(status) {
    if (status === "open") return "poll-chip";
    return "poll-chip poll-chip-soft";
  }

  function pollVisibilityLabel(mode) {
    if (mode === "live") return "نتیجه زنده";
    if (mode === "after-close") return "نتیجه بعد از پایان";
    if (mode === "creator-only") return "نتیجه فقط برای سازنده";
    return "نامشخص";
  }

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
          invalidServerResponse: true
        };

        if (/<!doctype html|<html/i.test(text || "")) {
          payload.error = "پاسخ غیرمنتظره از سرور دریافت شد. صفحه را تازه‌سازی کنید.";
        } else if (response.status === 401) {
          payload.error = "نشست شما منقضی شده است.";
        } else if (response.status >= 500) {
          payload.error = "خطای داخلی سرور رخ داد.";
        } else {
          payload.error = "پاسخ نامعتبر از سرور دریافت شد.";
        }
      }

      payload.httpStatus = response.status;
      return payload;
    });
  }

  function apiGet(action, paramsObj) {
    var query = new URLSearchParams(Object.assign({ action: action }, paramsObj || {}));
    return fetch("/chat/chat_api.php?" + query.toString(), {
      method: "GET",
      credentials: "same-origin",
      headers: {
        Accept: "application/json"
      }
    }).then(parseApiResponse).catch(function () {
      return {
        success: false,
        error: "ارتباط با سرور برقرار نشد.",
        networkError: true,
        httpStatus: 0
      };
    });
  }

  function apiPost(action, payload) {
    var body = new URLSearchParams(Object.assign({ action: action }, payload || {}));
    return fetch("/chat/chat_api.php", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        Accept: "application/json"
      },
      body: body
    }).then(parseApiResponse).catch(function () {
      return {
        success: false,
        error: "ارتباط با سرور برقرار نشد.",
        networkError: true,
        httpStatus: 0
      };
    });
  }

  function consumeUnauthorized(payload, fallbackText) {
    var auth = safeAuthApi();
    var text = fallbackText || "نشست شما منقضی شده است. دوباره وارد شوید.";

    try {
      if (auth && typeof auth.handleUnauthorizedPayload === "function") {
        if (auth.handleUnauthorizedPayload(payload, text)) {
          return true;
        }
      }
    } catch (_error) {
      // Ignore stale auth surface mismatch and continue fallback checks.
    }

    if (payload && (payload.loggedOut || payload.httpStatus === 401)) {
      if (auth && typeof auth.markUnauthorized === "function") {
        auth.markUnauthorized((payload && payload.error) || text);
      }
      return true;
    }

    return false;
  }

  function isPollCreatorRole(user) {
    if (!user) return false;
    var role = String(user.role || "").trim();
    return role === "owner" || role === "representative" || !!user.isOwner || !!user.isRepresentative;
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
        helper.style.pointerEvents = "none";
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

  var bootBox = $("boot-box");
  var loginBox = $("login-box");
  var accessBox = $("access-box");
  var pollsBox = $("polls-box");
  var loginLink = $("login-link");
  var accountLink = $("account-link");
  var creatorRoleCopy = $("creator-role-copy");

  var createCard = $("create-card");
  var createForm = $("create-form");
  var createBtn = $("create-poll-btn");
  var createFeedback = $("create-feedback");
  var addOptionBtn = $("add-option-btn");
  var optionsWrap = $("poll-options");
  var questionInput = $("poll-question");
  var multipleInput = $("poll-multiple");
  var maxChoicesInput = $("poll-max-choices");
  var anonymousInput = $("poll-anonymous");
  var allowVoteChangeInput = $("poll-allow-vote-change");
  var allowCreatorVoteInput = $("poll-allow-creator-vote");
  var resultVisibilityInput = $("poll-result-visibility");
  var audienceInput = $("poll-audience");
  var conversationSelect = $("poll-conversation");
  var postConversationInput = $("poll-post-conversation");
  var startAtInput = $("poll-start-at");
  var endAtInput = $("poll-end-at");

  var creatorNameChip = $("creator-name-chip");
  var reloadPollsBtn = $("reload-polls-btn");
  var pollList = $("poll-list");
  var pollListEmpty = $("poll-list-empty");
  var pollCountChip = $("poll-count-chip");
  var pollListTitle = $("poll-list-title");
  var pollListHint = $("poll-list-hint");
  var toastEl = $("toast");

  var createdBox = $("created-poll-box");
  var createdStatusChip = $("created-status-chip");
  var createdPollLink = $("created-poll-link");
  var createdCopyBtn = $("created-copy-btn");
  var createdOpenLink = $("created-open-link");

  var authState = {
    ready: false,
    loggedIn: false,
    user: null
  };
  var lastPolls = [];
  var toastTimer = null;
  var pendingLoad = false;

  function canCreatePoll(user) {
    return isPollCreatorRole(user);
  }

  function isCreatorView() {
    return authState.loggedIn && canCreatePoll(authState.user);
  }

  function hideAllStates() {
    bootBox.hidden = true;
    loginBox.hidden = true;
    accessBox.hidden = true;
    pollsBox.hidden = true;
  }

  function showToast(text) {
    if (!toastEl || !text) return;
    toastEl.textContent = text;
    toastEl.classList.add("is-show");
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () {
      toastEl.classList.remove("is-show");
    }, 2000);
  }

  function setFeedback(text, kind) {
    if (!createFeedback) return;
    createFeedback.textContent = text || "";
    createFeedback.className = "poll-feedback" + (kind ? " is-" + kind : "");
  }

  function setListModeUi() {
    var creatorView = isCreatorView();
    if (createCard) {
      createCard.hidden = !creatorView;
    }
    if (createdBox) {
      createdBox.hidden = !creatorView;
    }

    if (creatorView) {
      pollListTitle.textContent = "نظرسنجی‌های من / مدیریت‌پذیر";
      pollListHint.textContent = "این لیست شامل نظرسنجی‌هایی است که ساخته‌ای، رأی داده‌ای یا حق مدیریتشان را داری.";
      creatorRoleCopy.textContent = "حالت مدیریت نظرسنجی فعال است.";
      return;
    }

    pollListTitle.textContent = "نظرسنجی‌های فعال برای شما";
    pollListHint.textContent = "از اینجا می‌توانی در نظرسنجی‌هایی که برای گروه یا روتیشن تو فعال شده‌اند شرکت کنی.";
    creatorRoleCopy.textContent = "حالت شرکت در نظرسنجی‌ها فعال است.";
  }

  function addOptionRow(initialText) {
    var row = document.createElement("div");
    row.className = "poll-option-row";

    var input = document.createElement("input");
    input.type = "text";
    input.className = "poll-option-input";
    input.maxLength = 120;
    input.placeholder = "گزینه";
    input.value = initialText || "";
    row.appendChild(input);

    var removeBtn = document.createElement("button");
    removeBtn.type = "button";
    removeBtn.className = "poll-option-remove";
    removeBtn.textContent = "×";
    removeBtn.addEventListener("click", function () {
      if (optionsWrap.querySelectorAll(".poll-option-row").length <= 2) {
        showToast("حداقل دو گزینه لازم است.");
        return;
      }
      row.remove();
    });
    row.appendChild(removeBtn);

    optionsWrap.appendChild(row);
  }

  function resetOptionEditor() {
    if (!optionsWrap) return;
    optionsWrap.innerHTML = "";
    addOptionRow("");
    addOptionRow("");
  }

  function collectOptions() {
    var options = [];
    Array.prototype.forEach.call(optionsWrap.querySelectorAll(".poll-option-input"), function (input) {
      var text = String(input.value || "").trim();
      if (text) {
        options.push({ text: text });
      }
    });
    return options;
  }

  function syncMultipleChoiceUi() {
    if (!maxChoicesInput || !multipleInput) return;
    var enabled = !!multipleInput.checked;
    maxChoicesInput.disabled = !enabled;
    if (!enabled) {
      maxChoicesInput.value = "1";
    } else if (Number(maxChoicesInput.value || "0") < 2) {
      maxChoicesInput.value = "2";
    }
  }

  function renderConversations(conversations) {
    if (!conversationSelect) return;
    conversationSelect.innerHTML = '<option value="">فقط لینک (بدون ارسال در گفتگو)</option>';
    var list = Array.isArray(conversations) ? conversations : [];
    list.forEach(function (conversation) {
      if (!conversation || !conversation.id) return;
      var option = document.createElement("option");
      option.value = String(conversation.id);
      option.textContent = String(conversation.title || conversation.id);
      conversationSelect.appendChild(option);
    });

    var qsConversationId = new URLSearchParams(window.location.search).get("conversationId");
    if (qsConversationId) {
      conversationSelect.value = qsConversationId;
    }
  }

  function renderPollItem(poll) {
    var card = document.createElement("article");
    card.className = "poll-item";

    var head = document.createElement("div");
    head.className = "poll-item__head";
    var copy = document.createElement("div");

    var title = document.createElement("h3");
    title.className = "poll-item__title";
    title.textContent = String(poll.question || "نظرسنجی");
    copy.appendChild(title);

    var meta = document.createElement("p");
    meta.className = "poll-item__meta";
    var bits = [];
    bits.push(statusLabel(poll.status));
    bits.push(formatDateTime(poll.updatedAt));
    if (poll.audience && poll.audience.label) {
      bits.push("فعال برای: " + String(poll.audience.label));
    }
    if (poll.settings) {
      bits.push(poll.settings.anonymous ? "ناشناس" : "هویت‌دار");
      bits.push(poll.settings.multipleChoice ? "چندگزینه‌ای" : "تک‌گزینه‌ای");
      bits.push(pollVisibilityLabel(poll.settings.resultVisibility || ""));
    }
    meta.textContent = bits.join(" • ");
    copy.appendChild(meta);
    head.appendChild(copy);

    var chip = document.createElement("span");
    chip.className = statusClass(poll.status);
    chip.textContent = statusLabel(poll.status);
    head.appendChild(chip);
    card.appendChild(head);

    var note = document.createElement("p");
    note.className = "poll-note";
    if (poll.results && poll.results.visible) {
      var total = poll.results.totalVoters == null ? "—" : Number(poll.results.totalVoters).toLocaleString("fa-IR");
      note.textContent = "تعداد رأی‌دهنده: " + total;
    } else {
      note.textContent = String((poll.results && poll.results.hiddenReason) || "نتیجه این نظرسنجی فعلاً مخفی است.");
    }
    card.appendChild(note);

    var actions = document.createElement("div");
    actions.className = "poll-item__actions";

    var open = document.createElement("a");
    open.className = "poll-btn poll-btn-primary";
    open.href = String(poll.shareUrl || "/chat/poll/?poll=");
    open.target = "_blank";
    open.rel = "noopener";
    open.textContent = "باز کردن";
    actions.appendChild(open);

    var copyBtn = document.createElement("button");
    copyBtn.type = "button";
    copyBtn.className = "poll-btn poll-btn-ghost";
    copyBtn.textContent = "کپی لینک";
    copyBtn.addEventListener("click", function () {
      var url = window.location.origin + String(poll.shareUrl || "");
      copyText(url).then(function () {
        showToast("لینک کپی شد.");
      }).catch(function () {
        showToast("کپی لینک انجام نشد.");
      });
    });
    actions.appendChild(copyBtn);

    if (poll.permissions && poll.permissions.canClose) {
      var closeBtn = document.createElement("button");
      closeBtn.type = "button";
      closeBtn.className = "poll-btn poll-btn-danger";
      closeBtn.textContent = "بستن";
      closeBtn.addEventListener("click", function () {
        closePoll(poll.id);
      });
      actions.appendChild(closeBtn);
    }

    if (poll.permissions && poll.permissions.canReopen) {
      var reopenBtn = document.createElement("button");
      reopenBtn.type = "button";
      reopenBtn.className = "poll-btn poll-btn-ghost";
      reopenBtn.textContent = "بازگشایی";
      reopenBtn.addEventListener("click", function () {
        reopenPoll(poll.id);
      });
      actions.appendChild(reopenBtn);
    }

    if (poll.permissions && poll.permissions.canManage) {
      var deleteBtn = document.createElement("button");
      deleteBtn.type = "button";
      deleteBtn.className = "poll-btn poll-btn-danger";
      deleteBtn.textContent = "حذف";
      deleteBtn.addEventListener("click", function () {
        deletePoll(poll.id);
      });
      actions.appendChild(deleteBtn);
    }

    card.appendChild(actions);
    return card;
  }

  function renderPollList(polls) {
    lastPolls = Array.isArray(polls) ? polls.slice() : [];
    pollList.innerHTML = "";
    pollCountChip.textContent = Number(lastPolls.length).toLocaleString("fa-IR");
    pollListEmpty.hidden = lastPolls.length > 0;
    lastPolls.forEach(function (poll) {
      pollList.appendChild(renderPollItem(poll));
    });
  }

  function showCreatedPoll(poll) {
    if (!createdBox || !createdStatusChip || !createdPollLink || !createdOpenLink) {
      return;
    }
    if (!poll || !poll.shareUrl) {
      createdBox.hidden = true;
      return;
    }

    var url = window.location.origin + String(poll.shareUrl);
    createdBox.hidden = false;
    createdStatusChip.textContent = statusLabel(poll.status || "open");
    createdPollLink.value = url;
    createdOpenLink.href = String(poll.shareUrl);
  }

  async function loadData() {
    if (!authState.loggedIn || pendingLoad) {
      return;
    }

    pendingLoad = true;
    reloadPollsBtn.disabled = true;
    setFeedback("", "");
    try {
      var creatorView = isCreatorView();
      var response = await apiGet(creatorView ? "listPolls" : "activePolls");
      if (consumeUnauthorized(response)) return;
      if (!response || !response.success) {
        throw new Error((response && response.error) || "بارگذاری انجام نشد.");
      }

      if (creatorView) {
        renderConversations(response.conversations || []);
      } else {
        renderConversations([]);
      }

      renderPollList(response.polls || []);
      creatorNameChip.textContent = String((authState.user && authState.user.name) || "کاربر");
      setListModeUi();
    } catch (error) {
      setFeedback(error && error.message ? error.message : "بارگذاری داده انجام نشد.", "error");
    } finally {
      reloadPollsBtn.disabled = false;
      pendingLoad = false;
    }
  }

  async function closePoll(pollId) {
    if (!pollId || !window.confirm("این نظرسنجی بسته شود؟")) return;
    try {
      var response = await apiPost("closePoll", { pollId: String(pollId) });
      if (consumeUnauthorized(response)) return;
      if (!response || !response.success) {
        throw new Error((response && response.error) || "بستن نظرسنجی انجام نشد.");
      }
      showToast("نظرسنجی بسته شد.");
      await loadData();
    } catch (error) {
      showToast(error && error.message ? error.message : "بستن نظرسنجی انجام نشد.");
    }
  }

  async function reopenPoll(pollId) {
    if (!pollId || !window.confirm("این نظرسنجی بازگشایی شود؟")) return;
    try {
      var response = await apiPost("reopenPoll", { pollId: String(pollId) });
      if (consumeUnauthorized(response)) return;
      if (!response || !response.success) {
        throw new Error((response && response.error) || "بازگشایی انجام نشد.");
      }
      showToast("نظرسنجی بازگشایی شد.");
      await loadData();
    } catch (error) {
      showToast(error && error.message ? error.message : "بازگشایی انجام نشد.");
    }
  }

  async function deletePoll(pollId) {
    if (!pollId || !window.confirm("این نظرسنجی حذف شود؟")) return;
    try {
      var response = await apiPost("deletePoll", { pollId: String(pollId) });
      if (consumeUnauthorized(response)) return;
      if (!response || !response.success) {
        throw new Error((response && response.error) || "حذف نظرسنجی انجام نشد.");
      }
      showToast("نظرسنجی حذف شد.");
      if (isCreatorView()) {
        renderPollList(Array.isArray(response.polls) ? response.polls : []);
      } else {
        renderPollList(Array.isArray(response.activePolls) ? response.activePolls : []);
      }
    } catch (error) {
      showToast(error && error.message ? error.message : "حذف نظرسنجی انجام نشد.");
    }
  }

  async function submitCreate(event) {
    event.preventDefault();
    if (!authState.loggedIn || !isCreatorView()) return;

    var question = String(questionInput.value || "").trim();
    var options = collectOptions();
    if (!question) {
      setFeedback("سؤال نظرسنجی را وارد کن.", "error");
      return;
    }
    if (options.length < 2) {
      setFeedback("حداقل دو گزینه معتبر لازم است.", "error");
      return;
    }
    if (options.length > 12) {
      setFeedback("حداکثر ۱۲ گزینه مجاز است.", "error");
      return;
    }

    var multipleChoice = !!multipleInput.checked;
    var maxChoices = multipleChoice ? Math.max(2, Number(maxChoicesInput.value || "2")) : 1;
    maxChoices = Math.min(maxChoices, options.length);
    var conversationId = String(conversationSelect.value || "");
    var postInConversation = conversationId ? !!postConversationInput.checked : false;

    createBtn.disabled = true;
    setFeedback("در حال ساخت نظرسنجی...", "");
    try {
      var response = await apiPost("createPoll", {
        question: question,
        options: JSON.stringify(options),
        conversationId: conversationId,
        anonymous: anonymousInput.checked ? "1" : "0",
        multipleChoice: multipleChoice ? "1" : "0",
        maxChoices: String(maxChoices),
        allowVoteChange: allowVoteChangeInput.checked ? "1" : "0",
        allowCreatorVote: allowCreatorVoteInput.checked ? "1" : "0",
        resultVisibility: String(resultVisibilityInput.value || "live"),
        audience: String((audienceInput && audienceInput.value) || "link"),
        startAt: toIsoValue(startAtInput.value),
        endAt: toIsoValue(endAtInput.value),
        postInConversation: postInConversation ? "1" : "0"
      });

      if (consumeUnauthorized(response)) return;
      if (!response || !response.success) {
        throw new Error((response && response.error) || "ساخت نظرسنجی انجام نشد.");
      }

      createForm.reset();
      resetOptionEditor();
      syncMultipleChoiceUi();
      showCreatedPoll(response.poll || null);
      setFeedback("نظرسنجی با موفقیت ساخته شد.", "success");
      showToast("نظرسنجی ساخته شد.");

      if (Array.isArray(response.polls)) {
        renderPollList(response.polls);
      } else {
        await loadData();
      }
    } catch (error) {
      setFeedback(error && error.message ? error.message : "ساخت نظرسنجی انجام نشد.", "error");
    } finally {
      createBtn.disabled = false;
    }
  }

  function handleAuthState(detail) {
    authState.ready = true;
    authState.loggedIn = !!(detail && detail.loggedIn && detail.user);
    authState.user = detail && detail.user ? detail.user : null;

    hideAllStates();

    if (detail && (detail.status === "session-restoring" || detail.status === "logging-out")) {
      bootBox.hidden = false;
      return;
    }

    if (!authState.loggedIn) {
      loginBox.hidden = false;
      loginLink.href = window.Dent1402Auth.loginUrl(nowPath());
      accountLink.href = window.Dent1402Auth.loginUrl(nowPath());
      return;
    }

    accountLink.href = "/account/";
    accessBox.hidden = true;
    pollsBox.hidden = false;
    setListModeUi();
    loadData();
  }

  addOptionBtn.addEventListener("click", function () {
    if (!isCreatorView()) {
      return;
    }
    if (optionsWrap.querySelectorAll(".poll-option-row").length >= 12) {
      showToast("حداکثر ۱۲ گزینه مجاز است.");
      return;
    }
    addOptionRow("");
  });

  multipleInput.addEventListener("change", syncMultipleChoiceUi);
  createForm.addEventListener("submit", submitCreate);
  reloadPollsBtn.addEventListener("click", function () {
    loadData();
  });

  createdCopyBtn.addEventListener("click", function () {
    copyText(createdPollLink.value || "").then(function () {
      showToast("لینک کپی شد.");
    }).catch(function () {
      showToast("کپی لینک انجام نشد.");
    });
  });

  resetOptionEditor();
  syncMultipleChoiceUi();
  hideAllStates();
  bootBox.hidden = false;

  window.Dent1402Auth.onChange(function (detail) {
    handleAuthState(detail || {});
  });
})();
