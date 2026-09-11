from __future__ import annotations

import html
import logging
import os
import threading
import time
from typing import Any, Callable

from .app import DentBotApp
from .site_api import SiteApiError

_INSTALLED = False


def _env_bool(name: str, default: bool = False) -> bool:
    raw = os.getenv(name)
    if raw is None or not raw.strip():
        return default
    return raw.strip().lower() in {"1", "true", "yes", "on"}


def _platform_enabled(platform: str) -> bool:
    return _env_bool(f"DENT_CLASSOPS_{platform.upper()}_ENABLED", False)


def _destination_chat_id(platform: str, binding_ref: str) -> int | None:
    names = {
        "class_group": f"DENT_CLASSOPS_{platform.upper()}_CLASS_GROUP_ID",
        "information_channel": f"DENT_CLASSOPS_{platform.upper()}_INFORMATION_CHANNEL_ID",
    }
    env_name = names.get(binding_ref)
    if not env_name:
        return None
    raw = (os.getenv(env_name) or "").strip()
    try:
        value = int(raw)
    except (TypeError, ValueError):
        return None
    return value if value != 0 else None


def _safe_text(value: object, limit: int = 3500) -> str:
    text = str(value or "").replace("\x00", "").strip()
    return html.escape(text[:limit])


def _message_text(message: dict[str, Any]) -> str:
    title = _safe_text(message.get("title"), 180)
    body = _safe_text(message.get("body"), 3300)
    parts: list[str] = []
    if title:
        parts.append(f"<b>{title}</b>")
    if body:
        parts.append(body)
    return "\n\n".join(parts) or "<b>ClassOps</b>"


def dispatch_classops_delivery_batch(*, settings, api, state, site_api) -> dict[str, int]:
    counts = {"claimed": 0, "sent": 0, "acknowledged": 0, "failed": 0}
    platform = str(getattr(settings, "platform", "")).strip().lower()
    if platform not in {"telegram", "bale"} or not _platform_enabled(platform):
        return counts
    owner_id = int(getattr(settings, "owner_id", 0) or 0)
    if owner_id == 0:
        return counts
    result = site_api.request(
        "classopsDeliveryClaim",
        owner_id,
        limit=max(1, min(20, int(os.getenv("DENT_CLASSOPS_DELIVERY_BATCH_SIZE", "10") or "10"))),
    )
    deliveries = [row for row in result.get("deliveries", []) if isinstance(row, dict)]
    counts["claimed"] = len(deliveries)
    for delivery in deliveries:
        intent_id = str(delivery.get("intentId") or "")
        destination = dict(delivery.get("destination") or {})
        binding_ref = str(destination.get("bindingRef") or "")
        destination_platform = str(destination.get("platform") or "")
        receipt_id = f"classops-direct:{intent_id}"
        if (
            not intent_id.startswith("cdi_")
            or destination_platform != platform
            or binding_ref not in {"class_group", "information_channel"}
        ):
            counts["failed"] += 1
            if intent_id:
                try:
                    site_api.request(
                        "classopsDeliveryAck", owner_id, intentId=intent_id,
                        delivered=False, reasonCode="INVALID_DELIVERY",
                    )
                except SiteApiError:
                    pass
            continue
        if state.has_notification_delivery(receipt_id):
            site_api.request("classopsDeliveryAck", owner_id, intentId=intent_id, delivered=True, reasonCode="")
            counts["acknowledged"] += 1
            continue
        chat_id = _destination_chat_id(platform, binding_ref)
        if chat_id is None:
            counts["failed"] += 1
            try:
                site_api.request(
                    "classopsDeliveryAck", owner_id, intentId=intent_id,
                    delivered=False, reasonCode="DESTINATION_UNCONFIGURED",
                )
            except SiteApiError:
                pass
            continue
        try:
            api.send(chat_id, _message_text(dict(delivery.get("message") or {})), {"inline_keyboard": []})
            # Persist locally before server ACK. If ACK transport fails, a lease
            # retry is acknowledged without duplicating the external message.
            state.mark_notification_delivery(receipt_id)
            counts["sent"] += 1
            site_api.request("classopsDeliveryAck", owner_id, intentId=intent_id, delivered=True, reasonCode="")
            counts["acknowledged"] += 1
        except Exception as error:
            counts["failed"] += 1
            if error.__class__.__name__ not in {"BotApiError", "SiteApiError"}:
                logging.exception("ClassOps direct delivery failed unexpectedly")
            try:
                site_api.request(
                    "classopsDeliveryAck", owner_id, intentId=intent_id,
                    delivered=False, reasonCode="BOT_SEND_FAILED",
                )
            except SiteApiError:
                pass
    return counts


def _classops_background_loop(*, settings, api, state, site_api, platform_name: str, stop_event: threading.Event) -> None:
    platform = str(getattr(settings, "platform", "")).strip().lower()
    owner_id = int(getattr(settings, "owner_id", 0) or 0)
    next_delivery = 0.0
    next_scheduler = 0.0
    poll_seconds = max(5, min(300, int(os.getenv("DENT_CLASSOPS_RUNTIME_POLL_SECONDS", "15") or "15")))
    scheduler_seconds = max(15, min(300, int(os.getenv("DENT_CLASSOPS_SCHEDULER_POLL_SECONDS", "30") or "30")))
    while not stop_event.is_set():
        now = time.monotonic()
        if now >= next_delivery:
            try:
                counts = dispatch_classops_delivery_batch(settings=settings, api=api, state=state, site_api=site_api)
                if counts["claimed"]:
                    logging.info(
                        "%s ClassOps direct claimed=%s sent=%s acknowledged=%s failed=%s",
                        platform_name, counts["claimed"], counts["sent"], counts["acknowledged"], counts["failed"],
                    )
            except SiteApiError as error:
                logging.warning("%s ClassOps direct delivery unavailable code=%s", platform_name, error.code)
            next_delivery = time.monotonic() + poll_seconds
        now = time.monotonic()
        if now >= next_scheduler and owner_id != 0 and _platform_enabled(platform):
            try:
                # Both transports may request a tick; the website coordinator
                # lease elects exactly one execution path for external effects.
                result = site_api.request("classopsSchedulerTick", owner_id)
                if bool(result.get("claimed")):
                    logging.info("%s ClassOps scheduler coordinator tick completed", platform_name)
            except SiteApiError as error:
                logging.warning("%s ClassOps scheduler unavailable code=%s", platform_name, error.code)
            next_scheduler = time.monotonic() + scheduler_seconds
        stop_event.wait(1)


def _keyboard(rows: list[list[tuple[str, str]]]) -> dict[str, Any]:
    return {
        "inline_keyboard": [
            [{"text": label, "callback_data": data} for label, data in row]
            for row in rows
        ]
    }


def _website_button(app: DentBotApp) -> tuple[str, str] | None:
    site_url = str(getattr(app, "site_url", "") or "").rstrip("/")
    if not site_url.startswith("https://"):
        return None
    return ("🌐 مرکز عملیات وب", site_url + "/classops/")


def _menu_screen(app: DentBotApp, role: str, capabilities: dict[str, Any]) -> tuple[str, dict[str, Any]]:
    ai_state = str(dict(capabilities.get("ai") or {}).get("state") or "unconfigured")
    role_label = "مالک" if role == "owner" else "دانشجو"
    lines = [
        "<b>🧭 ClassOps</b>",
        "",
        f"نقش canonical: <b>{role_label}</b>",
        "همهٔ وضعیت‌ها از همان دادهٔ canonical سایت خوانده می‌شوند.",
    ]
    if role == "owner":
        lines.append("AI فقط draft می‌سازد و بدون تأیید تو هیچ چیزی منتشر نمی‌کند.")
        lines.append("وضعیت AI: " + ("آماده" if ai_state == "configured" else "تنظیم‌نشده / ورود دستی فعال"))
    rows = [
        [("📋 موارد", "classops:items"), ("🌤 خلاصه فردا", "classops:tomorrow")],
        [("🗓 خلاصه هفتگی", "classops:weekly")],
    ]
    if role == "owner":
        rows.append([("✍️ راهنمای draft", "classops:draft-help"), ("🤖 راهنمای AI", "classops:ai-help")])
    web = _website_button(app)
    if web:
        # URL buttons are represented separately because callback_data is not a URL.
        keyboard = _keyboard(rows)
        keyboard["inline_keyboard"].append([{"text": web[0], "url": web[1]}])
        return "\n".join(lines), keyboard
    return "\n".join(lines), _keyboard(rows)


def _extract_context(update: dict[str, Any]) -> tuple[dict[str, Any], dict[str, Any], dict[str, Any], int, int, str, str]:
    callback = dict(update.get("callback_query") or {})
    message = dict(callback.get("message") or update.get("message") or {})
    sender = dict(callback.get("from") or message.get("from") or {})
    chat = dict(message.get("chat") or {})
    try:
        chat_id = int(chat.get("id") or sender.get("id") or 0)
    except (TypeError, ValueError):
        chat_id = 0
    try:
        user_id = int(sender.get("id") or 0)
    except (TypeError, ValueError):
        user_id = 0
    text = str(message.get("text") or "").strip()
    data = str(callback.get("data") or "").strip()
    return callback, message, sender, chat, chat_id, user_id, text, data


def _send_error(app: DentBotApp, chat_id: int, error: SiteApiError) -> None:
    code = html.escape(str(getattr(error, "code", "CLASSOPS_UNAVAILABLE") or "CLASSOPS_UNAVAILABLE"))
    app.api.send(
        chat_id,
        "<b>⚠️ ClassOps در این درخواست قابل استفاده نیست.</b>\n\n"
        f"کد: <code>{code}</code>\n"
        "دادهٔ canonical تغییر نکرده است؛ در صورت نیاز از مرکز عملیات وب استفاده کن.",
        {"inline_keyboard": []},
    )


def _render_list(app: DentBotApp, chat_id: int, response: dict[str, Any]) -> None:
    data = dict(response.get("data") or {})
    items = [row for row in data.get("items", []) if isinstance(row, dict)]
    if not items:
        app.api.send(chat_id, "<b>📭 موردی برای نمایش نیست.</b>", _keyboard([[('↩️ ClassOps', 'classops:menu')]]))
        return
    lines = ["<b>📋 موارد ClassOps</b>", ""]
    rows: list[list[tuple[str, str]]] = []
    for item in items[:12]:
        item_id = str(item.get("id") or "")
        title = _safe_text(item.get("title") or "بدون عنوان", 80)
        kind = _safe_text(item.get("type") or "", 30)
        revision = int(item.get("revision") or 0)
        lines.append(f"• <b>{title}</b> — {kind} — r{revision}")
        if item_id.startswith("cop_"):
            rows.append([(f"مشاهده: {html.unescape(title)[:36]}", f"classops:item:{item_id}")])
    rows.append([("↩️ ClassOps", "classops:menu")])
    app.api.send(chat_id, "\n".join(lines), _keyboard(rows))


def _item_lines(item: dict[str, Any]) -> list[str]:
    lines = [
        f"<b>{_safe_text(item.get('title') or 'ClassOps', 180)}</b>",
        f"نوع: {_safe_text(item.get('type'), 40)} | revision: {int(item.get('revision') or 0)}",
        f"وضعیت: {_safe_text(item.get('status'), 40)}",
    ]
    description = _safe_text(item.get("description"), 2500)
    if description:
        lines += ["", description]
    timing = dict(item.get("timing") or {})
    for key, label in (("startsAt", "شروع"), ("endsAt", "پایان"), ("dueAt", "مهلت")):
        if timing.get(key):
            lines.append(f"{label}: <code>{_safe_text(timing.get(key), 80)}</code>")
    if item.get("location"):
        lines.append("مکان: " + _safe_text(item.get("location"), 220))
    task = dict(item.get("task") or {})
    if task:
        lines.append("وضعیت کار من: <b>" + _safe_text(task.get("state"), 40) + "</b>")
    ack = dict(item.get("ack") or {})
    if ack:
        lines.append("ACK این revision: " + ("✅ ثبت شده" if ack.get("acked") else "⏳ ثبت نشده"))
    service = dict(item.get("service") or {})
    if service:
        state = dict(service.get("state") or {})
        lines.append("یادآوری صبا: <b>" + _safe_text(state.get("state") or "pending", 40) + "</b>")
        lines.append("این وضعیت فقط محلی است و انجام کار در صبا را تأیید نمی‌کند.")
    return lines


def _render_item(app: DentBotApp, chat_id: int, response: dict[str, Any]) -> None:
    item = dict(response.get("item") or {})
    actions = dict(response.get("actions") or {})
    rows: list[list[tuple[str, str]]] = []
    labels = {
        "cancel": "لغو",
        "archive": "آرشیو",
        "task_submit": "ارسال شد",
        "task_complete": "تکمیل شد",
        "ack": "✅ تأیید کردم",
        "service_completed": "انجام شد (محلی)",
        "service_waived": "صرف‌نظر (محلی)",
    }
    for key in ("task_submit", "task_complete", "ack", "service_completed", "service_waived", "cancel", "archive"):
        token = str(actions.get(key) or "")
        if token.startswith("cxo_"):
            rows.append([(labels[key], token)])
    rows.append([("↩️ فهرست", "classops:items")])
    app.api.send(chat_id, "\n".join(_item_lines(item)), _keyboard(rows))


def _render_digest(app: DentBotApp, chat_id: int, response: dict[str, Any]) -> None:
    digest = dict(response.get("digest") or {})
    text = _safe_text(digest.get("plainText") or "موردی ثبت نشده است.", 3900)
    app.api.send(chat_id, text, _keyboard([[('↩️ ClassOps', 'classops:menu')]]))


def _render_ai_draft(app: DentBotApp, chat_id: int, response: dict[str, Any]) -> None:
    draft = dict(response.get("draft") or {})
    fields = dict(draft.get("fields") or {})
    unresolved = [str(value) for value in draft.get("unresolved", []) if value is not None]
    lines = ["<b>🤖 Draft پیشنهادی AI</b>", "", "این draft هنوز هیچ mutation یا send انجام نداده است."]
    for key in ("type", "title", "description", "location", "importance", "requireAck"):
        value = fields.get(key)
        if value is not None and value != "":
            lines.append(f"<b>{html.escape(key)}</b>: {_safe_text(value, 900)}")
    if unresolved:
        lines += ["", "<b>موارد حل‌نشده:</b>", _safe_text("، ".join(unresolved), 1200)]
    else:
        lines += ["", "برای resolve قطعی audience/time/course و تأیید نهایی، preview deterministic لازم است."]
    keyboard: dict[str, Any] = {"inline_keyboard": []}
    web = _website_button(app)
    if web:
        keyboard["inline_keyboard"].append([{"text": "ویرایش و تأیید در وب", "url": web[1]}])
    keyboard["inline_keyboard"].append([{"text": "↩️ ClassOps", "callback_data": "classops:menu"}])
    app.api.send(chat_id, "\n".join(lines), keyboard)


def _render_preview(app: DentBotApp, chat_id: int, response: dict[str, Any]) -> None:
    preview = dict(response.get("preview") or {})
    item = dict(preview.get("item") or {})
    audience = dict(preview.get("audience") or {})
    destinations = dict(preview.get("destinations") or {})
    token = str(response.get("confirmToken") or "")
    lines = ["<b>👁 پیش‌نمایش قبل از ثبت</b>", ""] + _item_lines(item)
    lines += [
        "",
        f"مخاطب: {int(audience.get('total') or 0)} نفر",
        f"هش مخاطب: <code>{_safe_text(audience.get('resolutionHash'), 72)}</code>",
        f"مقصدهای برنامه‌ریزی‌شده: {len(destinations)}",
        "",
        "AI یا preview هیچ ارسال مستقیمی انجام نداده است.",
    ]
    rows: list[list[tuple[str, str]]] = []
    if token.startswith("cxo_"):
        rows.append([("✅ تأیید و ثبت", token)])
    rows.append([("↩️ ClassOps", "classops:menu")])
    app.api.send(chat_id, "\n".join(lines), _keyboard(rows))


def _handle_classops(app: DentBotApp, update: dict[str, Any]) -> bool:
    callback, _message, _sender, chat, chat_id, user_id, text, data = _extract_context(update)
    command = text.split(maxsplit=1)[0].lower() if text else ""
    is_command = command in {"/classops", "/classops@dent1402bot"} or text.lower().startswith("/classops ")
    is_callback = data.startswith("classops:") or data.startswith("cxo_")
    if not is_command and not is_callback:
        return False
    if chat_id == 0 or user_id == 0:
        return True
    if str(chat.get("type") or "private") != "private":
        app.api.send(chat_id, "ClassOps فقط در گفت‌وگوی خصوصی ربات در دسترس است.", {"inline_keyboard": []})
        return True
    callback_id = str(callback.get("id") or "")
    if callback_id:
        try:
            app.api.answer_callback(callback_id, "در حال بررسی…")
        except Exception:
            pass
    try:
        if data.startswith("cxo_"):
            result = app.site_api.request("classopsResolveAction", user_id, token=data)
            app.api.send(chat_id, "<b>✅ عملیات روی وضعیت canonical ثبت شد.</b>", _keyboard([[('↩️ ClassOps', 'classops:menu')]]))
            return True
        nav = data[len("classops:"):] if data.startswith("classops:") else ""
        if nav == "menu" or (is_command and text.lower().strip() in {"/classops", "/classops@dent1402bot"}):
            response = app.site_api.request("classopsCapabilities", user_id)
            screen = _menu_screen(app, str(response.get("role") or "student"), dict(response.get("capabilities") or {}))
            app.api.send(chat_id, screen[0], screen[1])
            return True
        if nav == "items":
            _render_list(app, chat_id, app.site_api.request("classopsList", user_id, limit=20))
            return True
        if nav.startswith("item:cop_"):
            _render_item(app, chat_id, app.site_api.request("classopsGet", user_id, id=nav[5:]))
            return True
        if nav == "tomorrow":
            _render_digest(app, chat_id, app.site_api.request("classopsTomorrowSummary", user_id))
            return True
        if nav == "weekly":
            _render_digest(app, chat_id, app.site_api.request("classopsWeeklyDigest", user_id))
            return True
        if nav == "draft-help":
            app.api.send(
                chat_id,
                "<b>✍️ Draft دستی سریع</b>\n\n"
                "برای یک اطلاعیه ساده بنویس:\n"
                "<code>/classops draft عنوان | توضیحات</code>\n\n"
                "پیش‌نمایش مخاطب و مقصد ساخته می‌شود و ثبت فقط با دکمهٔ تأیید انجام می‌شود. "
                "برای task/exam/تغییر کلاس و فیلدهای پیچیده از مرکز عملیات وب استفاده کن.",
                _keyboard([[('↩️ ClassOps', 'classops:menu')]]),
            )
            return True
        if nav == "ai-help":
            app.api.send(
                chat_id,
                "<b>🤖 Draft با AI</b>\n\n"
                "بنویس: <code>/classops ai متن اطلاعیه یا عملیات</code>\n"
                "AI فقط draft ساختاری می‌سازد؛ اگر فیلدی نامشخص باشد null می‌ماند و برای تکمیل/تأیید به وب ارجاع داده می‌شود.",
                _keyboard([[('↩️ ClassOps', 'classops:menu')]]),
            )
            return True
        lower = text.lower()
        if lower.startswith("/classops ai "):
            owner_text = text[len("/classops ai "):].strip()
            response = app.site_api.request(
                "classopsOwnerAiDraft", user_id, ownerText=owner_text,
                cohortKey=os.getenv("DENT_CLASSOPS_DEFAULT_COHORT", "dentistry-1402"),
            )
            _render_ai_draft(app, chat_id, response)
            return True
        if lower.startswith("/classops draft "):
            body = text[len("/classops draft "):].strip()
            title, separator, description = body.partition("|")
            title = title.strip()
            description = description.strip() if separator else ""
            if not title:
                app.api.send(chat_id, "عنوان draft خالی است.", _keyboard([[('↩️ ClassOps', 'classops:menu')]]))
                return True
            request = {
                "item": {
                    "cohortKey": os.getenv("DENT_CLASSOPS_DEFAULT_COHORT", "dentistry-1402"),
                    "type": "announcement",
                    "title": title[:160],
                    "description": description[:4000],
                    "importance": "normal",
                    "requireAck": False,
                    "status": "draft",
                },
                "audienceSpec": {
                    "version": "classops-audience-v1",
                    "resolutionMode": "snapshot",
                    "expression": {"op": "whole_cohort"},
                    "includeStudentNumbers": [],
                    "excludeStudentNumbers": [],
                },
                "destinations": ["private_users"],
            }
            _render_preview(app, chat_id, app.site_api.request("classopsOwnerPreview", user_id, request=request))
            return True
        if is_command:
            app.api.send(chat_id, "دستور ClassOps شناخته نشد. /classops را بفرست.", _keyboard([[('↩️ ClassOps', 'classops:menu')]]))
            return True
        return False
    except SiteApiError as error:
        _send_error(app, chat_id, error)
        return True


def install_classops_runtime() -> None:
    """Install one shared semantic adapter for Telegram and Bale.

    The adapter monkey-patches only explicit ClassOps entry points and adds a
    companion background worker. All other Dent1402Bot behavior remains in the
    existing application/runtime implementation.
    """
    global _INSTALLED
    if _INSTALLED:
        return
    _INSTALLED = True

    original_handle: Callable[[DentBotApp, dict[str, Any]], None] = DentBotApp.handle

    def handle(self: DentBotApp, update: dict[str, Any]) -> None:
        if _handle_classops(self, update):
            return
        original_handle(self, update)

    DentBotApp.handle = handle  # type: ignore[method-assign]

    from . import runtime as runtime_module

    original_background = runtime_module._run_background_tasks

    def background_wrapper(*, settings, api, state, site_api, platform_name: str, stop_event: threading.Event) -> None:
        companion = threading.Thread(
            target=_classops_background_loop,
            kwargs={
                "settings": settings, "api": api, "state": state, "site_api": site_api,
                "platform_name": platform_name, "stop_event": stop_event,
            },
            name="dent-bot-classops",
            daemon=True,
        )
        companion.start()
        try:
            original_background(
                settings=settings, api=api, state=state, site_api=site_api,
                platform_name=platform_name, stop_event=stop_event,
            )
        finally:
            stop_event.set()
            companion.join(timeout=5)

    runtime_module._run_background_tasks = background_wrapper
