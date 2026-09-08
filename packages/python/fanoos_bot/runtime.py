from __future__ import annotations

import os
import tempfile
from dataclasses import dataclass
from pathlib import Path

from .activity import ActivityController
from .botapi import BotApiError
from .chunking import chunks
from .models import ActionResult, Screen


@dataclass(frozen=True)
class UpdateContext:
    subject: str
    chat_id: str
    private: bool
    message_id: int | None = None
    callback_id: str | None = None
    event_id: str | None = None


class DeliveryReceiptPump:
    """Retries transport delivery receipts without re-sending protected content."""

    def __init__(self, platform, backend, state):
        self.platform = platform
        self.backend = backend
        self.state = state

    def run_key(self, idempotency_key: str) -> bool:
        row = self.state.pending_delivery_receipt(idempotency_key)
        if not row:
            return False
        try:
            self.backend.delivery_receipt(
                row["platform"],
                row["workspace_id"],
                row["issuance_id"],
                row["idempotency_key"],
                row["outcome"],
                row.get("provider_ref"),
                row.get("error_code"),
            )
        except Exception:
            return False
        self.state.forget_delivery_receipt(idempotency_key)
        return True

    def run_once(self) -> bool:
        rows = self.state.pending_delivery_receipts(25)
        if not rows:
            return False
        for row in rows:
            if self.run_key(str(row["idempotency_key"])):
                return True
        return False


class BotRuntime:
    def __init__(self, platform, transport, application, state, *, activity=None):
        self.platform = platform
        self.transport = transport
        self.app = application
        self.state = state
        self.activity = activity or ActivityController(transport)
        self.delivery_receipts = DeliveryReceiptPump(platform, application.backend, state)

    def _message_result(self, ctx: UpdateContext, command: str, arg: str):
        if command.startswith("/start"):
            return self.app.start(ctx.subject, arg or None)
        if command == "/link":
            return self.app.link(ctx.subject, arg)
        if command == "/unlink":
            return self.app.unlink(ctx.subject)
        if command in {"/menu", "/home"}:
            return self.app.home(ctx.subject)
        if command in {"/workspace", "/workspaces"}:
            return self.app.workspaces(ctx.subject)
        if command == "/today":
            return self.app.day_schedule(ctx.subject, 0)
        if command == "/tomorrow":
            return self.app.day_schedule(ctx.subject, 1)
        if command == "/grades":
            return self.app.grades(ctx.subject)
        if command == "/announcements":
            return self.app.announcements(ctx.subject)
        if command == "/resources":
            return self.app.resources(ctx.subject)
        if command == "/buy":
            return self.app.create_order(
                ctx.subject,
                arg,
                f"bot-order:{self.platform}:{ctx.event_id}" if ctx.event_id else None,
            )
        if command == "/order":
            return self.app.order_status(ctx.subject, arg)
        if command == "/resource":
            return self.app.protected_resource(ctx.subject, arg)
        if command == "/update_server":
            return self.app.update_begin(ctx.subject, ctx.private)
        if command == "/update_status":
            return self.app.update_status(ctx.subject, ctx.private, arg or None)
        if command == "/help":
            return self.app.help()
        return self.app.home(ctx.subject)

    def handle_message(self, ctx: UpdateContext, text: str):
        command, *rest = (text or "").strip().split(maxsplit=1)
        arg = rest[0] if rest else ""

        if command == "/resource" and ctx.event_id:
            prior = self.state.processed_update(self.platform, ctx.event_id)
            if prior is not None:
                return prior

        with self.activity.operation(ctx.chat_id, private=ctx.private):
            result = self._message_result(ctx, command, arg)
        return self.deliver(ctx, result)

    def handle_callback(self, ctx: UpdateContext, value: str):
        # Callback acknowledgement is presentation feedback only. It deliberately
        # happens before idempotency reads or application/backend work and is
        # best-effort: an acknowledgement timeout must not block the action.
        if ctx.callback_id:
            try:
                self.transport.answer_callback(ctx.callback_id)
            except Exception:
                pass
        if ctx.event_id:
            prior = self.state.processed_update(self.platform, ctx.event_id)
            if prior is not None:
                return prior
        with self.activity.operation(ctx.chat_id, private=ctx.private):
            result = self.app.callback(ctx.subject, ctx.private, value)
        return self.deliver(ctx, result)

    def _queue_failed_receipt(self, result: ActionResult, error_code: str) -> None:
        if not result.receipt:
            return
        try:
            self.state.record_delivery_outcome(
                self.platform,
                result.receipt.workspace_id,
                result.receipt.issuance_id,
                result.receipt.idempotency_key,
                "failed",
                error_code=error_code,
            )
            self.delivery_receipts.run_key(result.receipt.idempotency_key)
        except Exception:
            try:
                self.app.backend.delivery_receipt(
                    self.platform,
                    result.receipt.workspace_id,
                    result.receipt.issuance_id,
                    result.receipt.idempotency_key,
                    "failed",
                    None,
                    error_code,
                )
            except Exception:
                pass

    def _record_success(self, ctx: UpdateContext, result: ActionResult, provider_ref: str) -> None:
        if not result.receipt:
            return
        self.state.record_delivery_outcome(
            self.platform,
            result.receipt.workspace_id,
            result.receipt.issuance_id,
            result.receipt.idempotency_key,
            "delivered",
            provider_ref=provider_ref,
            event_id=ctx.event_id,
        )
        self.delivery_receipts.run_key(result.receipt.idempotency_key)

    def _deliver_document(self, ctx: UpdateContext, result: ActionResult):
        document = result.document
        if document is None:
            raise RuntimeError("document result is missing payload")
        with tempfile.TemporaryDirectory(prefix="fanoos-bot-document-") as root:
            try:
                os.chmod(root, 0o700)
            except OSError:
                pass
            path = Path(root) / document.filename
            path.write_bytes(document.data)
            try:
                os.chmod(path, 0o600)
            except OSError:
                pass
            sent = self.transport.send_document(
                ctx.chat_id,
                path,
                caption=document.caption,
                protect_content=document.protect_content,
            )
        provider_ref = (
            str(sent.get("message_id"))
            if isinstance(sent, dict) and sent.get("message_id") is not None
            else "sent"
        )
        self._record_success(ctx, result, provider_ref)
        job_id = result.metadata.get("media_job_id") if isinstance(result.metadata, dict) else None
        if isinstance(job_id, str):
            try:
                self.app.media_state.forget(job_id, ctx.subject)
            except Exception:
                pass
        return provider_ref

    def deliver(self, ctx: UpdateContext, result: ActionResult):
        refs: list[str] = []
        screen = result.screen

        if result.receipt and not self.state.delivery_receipt_capacity_available(
            result.receipt.idempotency_key
        ):
            raise RuntimeError("delivery receipt outbox is full")

        if result.document is not None:
            try:
                return self._deliver_document(ctx, result)
            except BotApiError as exc:
                self._queue_failed_receipt(result, exc.code)
                raise

        limit = int(
            getattr(getattr(self.transport, "capabilities", None), "max_text_chars", 4096)
        )
        texts = chunks(screen.text, limit)
        if result.receipt and len(texts) != 1:
            self._queue_failed_receipt(result, "delivery_not_atomic")
            sent = self.transport.send_screen(
                ctx.chat_id,
                Screen(
                    "⚠️ این محتوای محافظت‌شده برای ارسال مستقیم در پیام‌رسان بیش از حد بزرگ است."
                ),
            )
            if isinstance(sent, dict) and sent.get("message_id") is not None:
                return str(sent["message_id"])
            return None

        try:
            for index, text in enumerate(texts):
                # Preserve the original Screen object for the common one-chunk
                # path. This keeps additive semantic metadata from Worker 3
                # available through getattr without creating a hard dependency.
                if len(texts) == 1:
                    part = screen
                else:
                    part = Screen(
                        text,
                        screen.rows if index == len(texts) - 1 else (),
                        edit=False,
                        protect_content=screen.protect_content,
                    )
                if part.edit and ctx.message_id is not None:
                    sent = self.transport.edit_screen(ctx.chat_id, ctx.message_id, part)
                else:
                    sent = self.transport.send_screen(ctx.chat_id, part)
                if isinstance(sent, dict) and sent.get("message_id") is not None:
                    refs.append(str(sent["message_id"]))

            provider_ref = refs[-1] if refs else "sent"
            self._record_success(ctx, result, provider_ref)
            return refs[-1] if refs else None
        except BotApiError as exc:
            self._queue_failed_receipt(result, exc.code)
            raise

    def flush_delivery_receipt_once(self) -> bool:
        return self.delivery_receipts.run_once()


class NotificationPump:
    def __init__(self, platform, backend, transport, state):
        self.platform = platform
        self.backend = backend
        self.transport = transport
        self.state = state

    def run_once(self):
        projection = self.backend.claim_notification(self.platform)
        delivery = projection.get("delivery") if isinstance(projection, dict) else None
        if not delivery:
            return False
        delivery_id = str(delivery["delivery_id"])
        prior = self.state.sent_delivery(delivery_id)
        idem = "notification:" + delivery_id
        if prior:
            self.backend.notification_receipt(
                self.platform,
                delivery_id,
                delivery["lease_token"],
                idem,
                "delivered",
                prior,
                None,
            )
            return True
        payload = delivery.get("payload") or {}
        text = (
            str(payload.get("title") or "")
            + "\n\n"
            + str(payload.get("body") or "")
        ).strip()
        try:
            ref = None
            limit = int(
                getattr(
                    getattr(self.transport, "capabilities", None),
                    "max_text_chars",
                    4096,
                )
            )
            for part in chunks(text, limit):
                sent = self.transport.send_screen(
                    str(delivery["subject"]),
                    Screen(part),
                )
                ref = (
                    str(sent.get("message_id"))
                    if isinstance(sent, dict) and sent.get("message_id") is not None
                    else ref
                )
            self.state.remember_delivery(delivery_id, ref or "sent")
            self.backend.notification_receipt(
                self.platform,
                delivery_id,
                delivery["lease_token"],
                idem,
                "delivered",
                ref,
                None,
            )
        except BotApiError as exc:
            self.backend.notification_receipt(
                self.platform,
                delivery_id,
                delivery["lease_token"],
                idem,
                "retry" if exc.transient else "failed",
                None,
                exc.code,
            )
        return True
