from __future__ import annotations

import logging
from dataclasses import dataclass
from typing import Any, Protocol

from .application import BotApplication
from .chunking import chunk_text
from .models import ActionResult
from .state import LocalState

log = logging.getLogger("fanoos_bot.runtime")


class MessagingTransport(Protocol):
    platform: str

    def send_text(self, chat_id: str, text: str, *, rows: list[list[Any]] | None = None, protect_content: bool = False) -> str: ...
    def edit_text(self, chat_id: str, message_id: str, text: str, *, rows: list[list[Any]] | None = None) -> str: ...
    def answer_callback(self, callback_id: str, text: str | None = None) -> None: ...


@dataclass(slots=True)
class UpdateContext:
    subject: str
    chat_id: str
    private_chat: bool
    message_id: str | None = None
    callback_id: str | None = None
    event_id: str | None = None


class BotRuntime:
    """Shared parsing/dispatch; each messenger supplies its own transport/process/state."""

    def __init__(self, app: BotApplication, transport: MessagingTransport, state: LocalState) -> None:
        self.app = app
        self.transport = transport
        self.state = state

    def handle_message(self, ctx: UpdateContext, text: str) -> None:
        command, arg = self._command((text or "").strip())
        if command == "start":
            result = self.app.start(ctx.subject, arg if (arg and self.app.platform == "telegram") else None)
        elif command == "link":
            result = self.app.link(ctx.subject, arg or "")
        elif command in {"menu", "home"}:
            result = self.app.home(ctx.subject)
        elif command in {"workspace", "workspaces"}:
            result = self.app.workspace_list(ctx.subject)
        elif command == "buy":
            idem = f"bot-order:{self.app.platform}:{ctx.event_id}" if ctx.event_id else None
            result = self.app.create_order(ctx.subject, arg or "", idem)
        elif command == "order":
            result = self.app.order_status(ctx.subject, arg or "")
        elif command == "resource":
            result = self.app.protected_resource(ctx.subject, arg or "")
        elif command == "update_server" and self.app.platform == "telegram":
            result = self.app.update_begin(ctx.subject, private_chat=ctx.private_chat)
        elif command == "update_status" and self.app.platform == "telegram":
            result = self.app.update_status(ctx.subject, arg, private_chat=ctx.private_chat)
        elif command == "help":
            result = self.app.help()
        else:
            result = self.app.home(ctx.subject)
        self._deliver(ctx, result, edit=False)

    def handle_callback(self, ctx: UpdateContext, data: str) -> None:
        if not ctx.callback_id:
            return
        try:
            self.transport.answer_callback(ctx.callback_id)
        except Exception as exc:
            log.warning("callback_ack_failed platform=%s error=%s", self.app.platform, type(exc).__name__)
        result = self.app.handle_callback(ctx.subject, data, private_chat=ctx.private_chat)
        self._deliver(ctx, result, edit=ctx.message_id is not None)

    def _deliver(self, ctx: UpdateContext, result: ActionResult, *, edit: bool) -> None:
        chunks = chunk_text(result.screen.text, 4096)
        provider_ref: str | None = None
        for index, chunk in enumerate(chunks):
            rows = result.screen.rows if index == len(chunks) - 1 else []
            try:
                if edit and index == 0 and ctx.message_id and not result.screen.protect_content:
                    provider_ref = self.transport.edit_text(ctx.chat_id, ctx.message_id, chunk, rows=rows)
                else:
                    provider_ref = self.transport.send_text(ctx.chat_id, chunk, rows=rows, protect_content=result.screen.protect_content)
            except Exception:
                if result.delivery_receipt:
                    self._delivery_receipt(result, "failed", None, "transport_send_failed")
                raise
        if result.delivery_receipt:
            self._delivery_receipt(result, "delivered", provider_ref, None)

    def _delivery_receipt(self, result: ActionResult, outcome: str, provider_ref: str | None, error_code: str | None) -> None:
        receipt = result.delivery_receipt
        if receipt is None:
            return
        try:
            self.app.backend.delivery_receipt({
                "platform": self.app.platform,
                "workspace_id": receipt.workspace_id,
                "issuance_id": receipt.issuance_id,
                "idempotency_key": receipt.idempotency_key,
                "outcome": outcome,
                "provider_message_ref": provider_ref,
                "error_code": error_code,
            })
        except Exception as exc:
            log.error("delivery_receipt_failed platform=%s error=%s", self.app.platform, type(exc).__name__)

    @staticmethod
    def _command(text: str) -> tuple[str, str | None]:
        if not text.startswith("/"):
            return "", None
        parts = text.split(maxsplit=1)
        command = parts[0][1:].split("@", 1)[0].lower()
        return command, parts[1].strip() if len(parts) == 2 else None


class NotificationPump:
    """Leased canonical notification delivery for one messenger adapter."""

    def __init__(self, app: BotApplication, transport: MessagingTransport, state: LocalState) -> None:
        self.app = app
        self.transport = transport
        self.state = state

    def once(self) -> bool:
        result = self.app.backend.claim_notification(self.app.platform)
        if not isinstance(result, dict) or result.get("delivery") is None:
            return False
        delivery = result.get("delivery")
        if not isinstance(delivery, dict):
            return False
        required = ("delivery_id", "lease_token", "subject", "payload", "attempt")
        if any(key not in delivery for key in required):
            return False
        delivery_id = str(delivery["delivery_id"])
        lease_token = str(delivery["lease_token"])
        subject = str(delivery["subject"])
        attempt = int(delivery["attempt"])
        idem = f"notification-{delivery_id}-{attempt}"

        prior_ref = self.state.sent_delivery(delivery_id)
        if prior_ref:
            self._receipt(delivery_id, lease_token, idem, "delivered", prior_ref, None)
            return True

        payload = delivery.get("payload")
        if not isinstance(payload, dict):
            self._receipt(delivery_id, lease_token, idem, "failed", None, "payload_invalid")
            return True
        title = str(payload.get("title") or "اطلاعیه فانوس")
        body = str(payload.get("body") or "")
        text = title if not body else f"{title}\n\n{body}"
        try:
            provider_ref = ""
            for chunk in chunk_text(text, 4096):
                provider_ref = self.transport.send_text(subject, chunk, rows=[])
            self.state.remember_sent_delivery(delivery_id, provider_ref)
            self._receipt(delivery_id, lease_token, idem, "delivered", provider_ref, None)
        except Exception as exc:
            code = getattr(exc, "safe_code", "transport_error")
            retryable = bool(getattr(exc, "retryable", False))
            self._receipt(delivery_id, lease_token, idem, "retry" if retryable else "failed", None, str(code)[:120])
        return True

    def _receipt(self, delivery_id: str, lease_token: str, idempotency_key: str, outcome: str, provider_ref: str | None, error_code: str | None) -> None:
        self.app.backend.notification_receipt({
            "platform": self.app.platform,
            "delivery_id": delivery_id,
            "lease_token": lease_token,
            "idempotency_key": idempotency_key,
            "outcome": outcome,
            "provider_message_ref": provider_ref,
            "error_code": error_code,
        })
