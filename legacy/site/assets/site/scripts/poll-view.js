(function () {
  "use strict";

  if (!window.Dent1402Auth) {
    return;
  }

  function $(id) {
    return document.getElementById(id);
  }

  function asObject(value) {
    return value && typeof value === "object" ? value : null;
  }

  function safeAuthApi() {
    return window.Dent1402Auth && typeof window.Dent1402Auth === "object"
      ? window.Dent1402Auth
      : null;
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

      if (!asObject(payload)) {
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

  var params = new URLSearchParams(window.location.search);
  var pollId = String(params.get("poll") || params.get("pollId") || "").trim();

  var bootBox = $("boot-box");
  var loginBox = $("login-box");
  var notFoundBox = $("not-found-box");
  var pollBox = $("poll-box");
  var loginLink = $("login-link");

  var pollSubtitle = $("poll-subtitle");
  var pollTitle = $("poll-title");
  var pollStatusChip = $("poll-status-chip");
  var pollMeta = $("poll-meta");
  var pollQuestion = $("poll-question");
  var voteForm = $("vote-form");
  var voteBtn = $("vote-btn");
  var voteFeedback = $("vote-feedback");

  var resultsTotalChip = $("results-total-chip");
  var resultsHiddenNote = $("results-hidden-note");
  var resultsList = $("results-list");

  var manageBox = $("manage-box");
  var closePollBtn = $("close-poll-btn");
  var reopenPollBtn = $("reopen-poll-btn");

  var shareLinkInput = $("share-link");
  var copyLinkBtn = $("copy-link-btn");
  var conversationLink = $("conversation-link");
  var pollsCenterLink = $("polls-center-link");
  var refreshBtn = $("refresh-btn");
  var toastEl = $("toast");

  var authUser = null;
  var currentPoll = null;
  var toastTimer = null;
  var pendingFetch = false;

  function showToast(text) {
    if (!toastEl || !text) return;
    toastEl.textContent = text;
    toastEl.classList.add("is-show");
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () {
      toastEl.classList.remove("is-show");
    }, 1900);
  }

  function setVoteFeedback(text, kind) {
    voteFeedback.textContent = text || "";
    voteFeedback.className = "poll-feedback" + (kind ? " is-" + kind : "");
  }

  function hideAllStages() {
    bootBox.hidden = true;
    loginBox.hidden = true;
    notFoundBox.hidden = true;
    pollBox.hidden = true;
  }

  function createVoteOption(option, inputType) {
    var label = document.createElement("label");
    label.className = "poll-vote-option";
    if (option && option.isSelected) {
      label.classList.add("is-selected");
    }

    var input = document.createElement("input");
    input.type = inputType;
    input.name = "poll-option";
    input.value = String(option.id || "");
    input.checked = !!option.isSelected;
    input.addEventListener("change", function () {
      syncSelectedStyles();
    });
    label.appendChild(input);

    var text = document.createElement("span");
    text.textContent = String(option.text || "");
    label.appendChild(text);
    return label;
  }

  function syncSelectedStyles() {
    Array.prototype.forEach.call(voteForm.querySelectorAll(".poll-vote-option"), function (row) {
      var input = row.querySelector("input");
      row.classList.toggle("is-selected", !!(input && input.checked));
    });
  }

  function selectedOptionIds() {
    var ids = [];
    Array.prototype.forEach.call(voteForm.querySelectorAll("input[name='poll-option']"), function (input) {
      if (input.checked) {
        ids.push(String(input.value || ""));
      }
    });
    return ids;
  }

  function renderResults(poll) {
    resultsList.innerHTML = "";
    if (!poll || !poll.results) {
      resultsHiddenNote.hidden = false;
      resultsHiddenNote.textContent = "اطلاعات نتیجه موجود نیست.";
      resultsTotalChip.textContent = "—";
      return;
    }

    if (!poll.results.visible) {
      resultsHiddenNote.hidden = false;
      resultsHiddenNote.className = "poll-note is-error";
      resultsHiddenNote.textContent = String(poll.results.hiddenReason || "نتیجه تا پایان نظرسنجی مخفی است.");
      resultsTotalChip.textContent = "مخفی";
      return;
    }

    resultsHiddenNote.hidden = true;
    resultsTotalChip.textContent = (poll.results.totalVoters == null ? "۰" : Number(poll.results.totalVoters).toLocaleString("fa-IR")) + " رأی‌دهنده";

    var options = Array.isArray(poll.options) ? poll.options : [];
    options.forEach(function (option) {
      var item = document.createElement("div");
      item.className = "poll-result-item";

      var head = document.createElement("div");
      head.className = "poll-result-item__head";

      var title = document.createElement("strong");
      title.textContent = String(option.text || "");
      head.appendChild(title);

      var meta = document.createElement("span");
      var count = option.voteCount == null ? "۰" : Number(option.voteCount).toLocaleString("fa-IR");
      var percent = option.percent == null ? "۰" : Number(option.percent).toLocaleString("fa-IR");
      meta.textContent = count + " • " + percent + "%";
      head.appendChild(meta);
      item.appendChild(head);

      var bar = document.createElement("div");
      bar.className = "poll-result-bar";
      var fill = document.createElement("i");
      fill.style.width = String(Math.max(0, Math.min(100, Number(option.percent || 0)))) + "%";
      bar.appendChild(fill);
      item.appendChild(bar);

      if (poll.results.identitiesVisible && Array.isArray(option.voters) && option.voters.length) {
        var votersList = document.createElement("ul");
        votersList.className = "poll-voters";
        option.voters.forEach(function (voter) {
          var li = document.createElement("li");
          li.textContent = String(voter.name || "کاربر");
          votersList.appendChild(li);
        });
        item.appendChild(votersList);
      }

      resultsList.appendChild(item);
    });
  }

  function renderPoll(poll) {
    currentPoll = poll || null;
    if (!poll) return;

    pollTitle.textContent = String(poll.question || "نظرسنجی");
    pollQuestion.textContent = String(poll.question || "");
    pollStatusChip.className = statusClass(poll.status);
    pollStatusChip.textContent = statusLabel(poll.status);
    pollSubtitle.textContent = poll.status === "scheduled"
      ? "این نظرسنجی هنوز شروع نشده است."
      : "رأی‌گیری با حساب واقعی انجام می‌شود.";

    var creatorName = poll.createdBy && poll.createdBy.name ? poll.createdBy.name : "سازنده";
    var metaBits = [
      "سازنده: " + creatorName,
      "ایجاد: " + formatDateTime(poll.createdAt)
    ];
    if (poll.endAt) {
      metaBits.push("پایان: " + formatDateTime(poll.endAt));
    }
    if (poll.audience && poll.audience.label) {
      metaBits.push("فعال برای: " + String(poll.audience.label));
    }
    pollMeta.textContent = metaBits.join(" • ");

    var multipleChoice = !!(poll.settings && poll.settings.multipleChoice);
    voteForm.innerHTML = "";
    (Array.isArray(poll.options) ? poll.options : []).forEach(function (option) {
      voteForm.appendChild(createVoteOption(option, multipleChoice ? "checkbox" : "radio"));
    });
    syncSelectedStyles();

    var canVote = !!(poll.viewer && poll.viewer.canVote);
    voteBtn.disabled = !canVote;
    voteBtn.textContent = canVote
      ? ((poll.viewer && poll.viewer.hasVoted) ? "ثبت تغییر رأی" : "ثبت رأی")
      : "رأی‌گیری غیرفعال";

    if (!canVote && poll.viewer && poll.viewer.hasVoted && !(poll.viewer.canChangeVote)) {
      setVoteFeedback("تغییر رأی در این نظرسنجی غیرفعال است.", "");
    } else if (poll.status === "scheduled") {
      setVoteFeedback("نظرسنجی در زمان‌بندی آینده شروع می‌شود.", "");
    } else if (poll.status === "closed") {
      setVoteFeedback("نظرسنجی بسته شده است.", "");
    } else {
      setVoteFeedback("", "");
    }

    renderResults(poll);

    manageBox.hidden = !(poll.permissions && poll.permissions.canManage);
    closePollBtn.hidden = !(poll.permissions && poll.permissions.canClose);
    reopenPollBtn.hidden = !(poll.permissions && poll.permissions.canReopen);

    var shareUrl = String(poll.shareUrl || ("/chat/poll/?poll=" + encodeURIComponent(poll.id || pollId)));
    shareLinkInput.value = window.location.origin + shareUrl;

    if (poll.conversation && poll.conversation.id) {
      conversationLink.hidden = false;
      conversationLink.href = "/chat/?conversationId=" + encodeURIComponent(String(poll.conversation.id));
      conversationLink.textContent = "گفتگوی مرتبط: " + String(poll.conversation.title || poll.conversation.id);
    } else {
      conversationLink.hidden = true;
      conversationLink.removeAttribute("href");
    }

    pollsCenterLink.hidden = !isPollCreatorRole(authUser);
  }

  async function fetchPoll() {
    if (!authUser || !pollId || pendingFetch) return;
    pendingFetch = true;
    refreshBtn.disabled = true;
    try {
      var response = await apiGet("poll", { poll: pollId });
      if (consumeUnauthorized(response)) return;

      if (response && response.httpStatus === 404) {
        hideAllStages();
        notFoundBox.hidden = false;
        return;
      }

      if (!response || !response.success || !response.poll) {
        throw new Error((response && response.error) || "بارگذاری نظرسنجی انجام نشد.");
      }

      hideAllStages();
      pollBox.hidden = false;
      renderPoll(response.poll);
    } catch (error) {
      setVoteFeedback(error && error.message ? error.message : "بارگذاری انجام نشد.", "error");
    } finally {
      pendingFetch = false;
      refreshBtn.disabled = false;
    }
  }

  async function submitVote() {
    if (!currentPoll || !currentPoll.viewer || !currentPoll.viewer.canVote) return;
    var selected = selectedOptionIds();
    if (!selected.length) {
      setVoteFeedback("حداقل یک گزینه را انتخاب کن.", "error");
      return;
    }

    voteBtn.disabled = true;
    setVoteFeedback("در حال ثبت رأی...", "");
    try {
      var response = await apiPost("votePoll", {
        pollId: String(currentPoll.id || pollId),
        optionIds: JSON.stringify(selected)
      });
      if (consumeUnauthorized(response)) return;
      if (!response || !response.success || !response.poll) {
        throw new Error((response && response.error) || "ثبت رأی انجام نشد.");
      }

      renderPoll(response.poll);
      setVoteFeedback("رأی با موفقیت ثبت شد.", "success");
      showToast("رأی ثبت شد.");
    } catch (error) {
      setVoteFeedback(error && error.message ? error.message : "ثبت رأی انجام نشد.", "error");
    } finally {
      if (currentPoll && currentPoll.viewer && currentPoll.viewer.canVote) {
        voteBtn.disabled = false;
      }
    }
  }

  async function closePoll() {
    if (!currentPoll || !currentPoll.id || !window.confirm("این نظرسنجی بسته شود؟")) return;
    try {
      var response = await apiPost("closePoll", { pollId: String(currentPoll.id) });
      if (consumeUnauthorized(response)) return;
      if (!response || !response.success || !response.poll) {
        throw new Error((response && response.error) || "بستن نظرسنجی انجام نشد.");
      }
      renderPoll(response.poll);
      showToast("نظرسنجی بسته شد.");
    } catch (error) {
      showToast(error && error.message ? error.message : "بستن انجام نشد.");
    }
  }

  async function reopenPoll() {
    if (!currentPoll || !currentPoll.id || !window.confirm("این نظرسنجی بازگشایی شود؟")) return;
    try {
      var response = await apiPost("reopenPoll", { pollId: String(currentPoll.id) });
      if (consumeUnauthorized(response)) return;
      if (!response || !response.success || !response.poll) {
        throw new Error((response && response.error) || "بازگشایی انجام نشد.");
      }
      renderPoll(response.poll);
      showToast("نظرسنجی بازگشایی شد.");
    } catch (error) {
      showToast(error && error.message ? error.message : "بازگشایی انجام نشد.");
    }
  }

  function handleAuth(detail) {
    hideAllStages();

    if (!pollId) {
      notFoundBox.hidden = false;
      notFoundBox.querySelector("p").textContent = "شناسه نظرسنجی در لینک وجود ندارد.";
      return;
    }

    if (detail && (detail.status === "session-restoring" || detail.status === "logging-out")) {
      bootBox.hidden = false;
      return;
    }

    if (!detail || !detail.loggedIn || !detail.user) {
      loginBox.hidden = false;
      loginLink.href = window.Dent1402Auth.loginUrl(window.location.pathname + window.location.search);
      return;
    }

    authUser = detail.user;
    fetchPoll();
  }

  voteBtn.addEventListener("click", submitVote);
  closePollBtn.addEventListener("click", closePoll);
  reopenPollBtn.addEventListener("click", reopenPoll);
  refreshBtn.addEventListener("click", fetchPoll);

  copyLinkBtn.addEventListener("click", function () {
    var value = String(shareLinkInput.value || "");
    if (!value) return;
    copyText(value).then(function () {
      showToast("لینک کپی شد.");
    }).catch(function () {
      showToast("کپی لینک انجام نشد.");
    });
  });

  hideAllStages();
  bootBox.hidden = false;

  window.Dent1402Auth.onChange(function (detail) {
    handleAuth(detail || {});
  });
})();
