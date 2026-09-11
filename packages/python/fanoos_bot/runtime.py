from __future__ import annotations

import os
import tempfile
from dataclasses import dataclass
from pathlib import Path

from .activity import ActivityController
from .botapi import BotApiError
from .chunking import chunks
from .models import ActionResult, Screen
from .presentation import notification_detail_screen
from .ui_v3.providers import ProviderContext


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
                row["platform"], row["workspace_id"], row["issuance_id"],
                row["idempotency_key"], row["outcome"], row.get("provider_ref"), row.get("error_code"),
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

    def _remember_processed_update(self, ctx: UpdateContext, provider_ref: str) -> None:
        recorder = getattr(self.state, "record_processed_update", None)
        if ctx.event_id and callable(recorder):
            recorder(self.platform, ctx.event_id, provider_ref)

    def _message_result(self, ctx: UpdateContext, command: str, arg: str):
        if command.startswith("/start"):
            return self.app.start(ctx.subject, arg or None)
        if command == "/link": return self.app.link(ctx.subject, arg)
        if command == "/unlink": return self.app.unlink(ctx.subject)
        if command in {"/menu", "/home"}: return self.app.home(ctx.subject)
        if command in {"/workspace", "/workspaces"}: return self.app.workspaces(ctx.subject)
        if command == "/courses": return self.app.courses(ctx.subject)
        if command == "/schedule": return self.app.schedule_menu(ctx.subject)
        if command == "/today": return self.app.day_schedule(ctx.subject, 0)
        if command == "/tomorrow": return self.app.day_schedule(ctx.subject, 1)
        if command == "/grades": return self.app.grades(ctx.subject)
        if command == "/announcements": return self.app.announcements(ctx.subject)
        if command == "/forms": return self.app.forms(ctx.subject)
        if command == "/resources": return self.app.resources(ctx.subject)
        if command == "/buy":
            # Product IDs are an implementation detail. Purchase starts from
            # the canonical catalog/website handoff; order creation remains a
            # backend-owned technical method for already-integrated callers.
            return self.app.payments(ctx.subject)
        if command == "/order": return self.app.order_status(ctx.subject, arg)
        if command == "/resource": return self.app.protected_resource(ctx.subject, arg)
        if command == "/update_server": return self.app.update_begin(ctx.subject, ctx.private)
        if command == "/update_status": return self.app.update_status(ctx.subject, ctx.private, arg or None)
        if command == "/help": return self.app.help()
        return self.app.home(ctx.subject)

    def _prepare_result(self, ctx: UpdateContext, result: ActionResult) -> ActionResult:
        prepare = getattr(self.app, "prepare_result", None)
        if callable(prepare):
            return prepare(ctx.subject, ctx.private, result)
        return result

    @staticmethod
    def _provider_context(ctx: UpdateContext, result: ActionResult) -> ProviderContext:
        raw_permissions = result.metadata.get("canonical_permissions", ()) if isinstance(result.metadata, dict) else ()
        permissions = frozenset(str(value) for value in raw_permissions if str(value))
        return ProviderContext(
            private_chat=ctx.private,
            callback_query=bool(ctx.callback_id),
            current_message_id=ctx.message_id,
            canonical_permissions=permissions,
        )

    def _send_screen(self, ctx: UpdateContext, result: ActionResult, screen: Screen, *, reply_to: int | None = None):
        if getattr(self.transport, "supports_v3_context", False):
            return self.transport.send_screen(
                ctx.chat_id, screen, reply_to=reply_to,
                context=self._provider_context(ctx, result),
            )
        return self.transport.send_screen(ctx.chat_id, screen)

    def _edit_screen(self, ctx: UpdateContext, result: ActionResult, screen: Screen):
        if getattr(self.transport, "supports_v3_context", False):
            return self.transport.edit_screen(
                ctx.chat_id, ctx.message_id, screen,
                context=self._provider_context(ctx, result),
            )
        return self.transport.edit_screen(ctx.chat_id, ctx.message_id, screen)

    def handle_message(self, ctx: UpdateContext, text: str):
        command, *rest = (text or "").strip().split(maxsplit=1)
        arg = rest[0] if rest else ""
        if ctx.event_id:
            prior = self.state.processed_update(self.platform, ctx.event_id)
            if prior is not None:
                return prior
        with self.activity.operation(ctx.chat_id, private=ctx.private):
            result = self._message_result(ctx, command, arg)
            try:
                result = self._prepare_result(ctx, result)
            except Exception as exc:
                self._remember_processed_update(ctx, f"failed:{type(exc).__name__}")
                raise
        try:
            provider_ref = self.deliver(ctx, result)
        except Exception as exc:
            # Application logic has already run. Persist transport/rendering
            # consumption so a duplicate update cannot replay a mutation.
            self._remember_processed_update(ctx, f"failed:{type(exc).__name__}")
            raise
        if not result.receipt:
            self._remember_processed_update(ctx, str(provider_ref or "sent"))
        return provider_ref

    def handle_callback(self, ctx: UpdateContext, value: str):
        # ACK remains before dedupe, backend calls and all rendering work.
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
            try:
                result = self._prepare_result(ctx, result)
            except Exception as exc:
                self._remember_processed_update(ctx, f"failed:{type(exc).__name__}")
                raise
        try:
            provider_ref = self.deliver(ctx, result)
        except Exception as exc:
            self._remember_processed_update(ctx, f"failed:{type(exc).__name__}")
            raise
        if not result.receipt:
            self._remember_processed_update(ctx, str(provider_ref or "sent"))
        return provider_ref

    def _queue_failed_receipt(self, result: ActionResult, error_code: str) -> None:
        if not result.receipt:
            return
        try:
            self.state.record_delivery_outcome(
                self.platform, result.receipt.workspace_id, result.receipt.issuance_id,
                result.receipt.idempotency_key, "failed", error_code=error_code,
            )
            self.delivery_receipts.run_key(result.receipt.idempotency_key)
        except Exception:
            try:
                self.app.backend.delivery_receipt(
                    self.platform, result.receipt.workspace_id, result.receipt.issuance_id,
                    result.receipt.idempotency_key, "failed", None, error_code,
                )
            except Exception:
                pass

    def _record_success(self, ctx: UpdateContext, result: ActionResult, provider_ref: str) -> None:
        if not result.receipt:
            return
        self.state.record_delivery_outcome(
            self.platform, result.receipt.workspace_id, result.receipt.issuance_id,
            result.receipt.idempotency_key, "delivered", provider_ref=provider_ref,
            event_id=ctx.event_id,
        )
        self.delivery_receipts.run_key(result.receipt.idempotency_key)

    def _deliver_document(self, ctx: UpdateContext, result: ActionResult):
        document = result.document
        if document is None:
            raise RuntimeError("document result is missing payload")
        with tempfile.TemporaryDirectory(prefix="fanoos-bot-document-") as root:
            try: os.chmod(root, 0o700)
            except OSError: pass
            path = Path(root) / document.filename
            path.write_bytes(document.data)
            try: os.chmod(path, 0o600)
            except OSError: pass
            sent = self.transport.send_document(
                ctx.chat_id, path, caption=document.caption,
                protect_content=document.protect_content,
            )
        provider_ref = str(sent.get("message_id")) if isinstance(sent, dict) and sent.get("message_id") is not None else "sent"
        self._record_success(ctx, result, provider_ref)
        job_id = result.metadata.get("media_job_id") if isinstance(result.metadata, dict) else None
        if isinstance(job_id, str):
            try: self.app.media_state.forget(job_id, ctx.subject)
            except Exception: pass
        return provider_ref

    def deliver(self, ctx: UpdateContext, result: ActionResult):
        refs: list[str] = []
        screen = result.screen
        if result.receipt and not self.state.delivery_receipt_capacity_available(result.receipt.idempotency_key):
            raise RuntimeError("delivery receipt outbox is full")

        if result.document is not None:
            try:
                return self._deliver_document(ctx, result)
            except BotApiError as exc:
                self._queue_failed_receipt(result, exc.code)
                raise

        limit = int(getattr(getattr(self.transport, "capabilities", None), "max_text_chars", 4096))
        texts = chunks(screen.text, limit)
        if result.receipt and len(texts) != 1:
            self._queue_failed_receipt(result, "delivery_not_atomic")
            sent = self._send_screen(
                ctx, result,
                Screen("⚠️ این محتوای محافظت‌شده برای ارسال مستقیم در پیام‌رسان بیش از حد بزرگ است."),
            )
            if isinstance(sent, dict) and sent.get("message_id") is not None:
                return str(sent["message_id"])
            return None

        try:
            for index, text in enumerate(texts):
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
                    sent = self._edit_screen(ctx, result, part)
                else:
                    sent = self._send_screen(ctx, result, part)
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
        if not isinstance(delivery, dict):
            return False
        delivery_id = str(delivery.get("delivery_id") or "")
        lease_token = str(delivery.get("lease_token") or "")
        subject = str(delivery.get("subject") or "")
        if not delivery_id or not lease_token or not subject:
            return False
        prior = self.state.sent_delivery(delivery_id)
        idem = "notification:" + delivery_id
        if prior:
            self.backend.notification_receipt(
                self.platform, delivery_id, lease_token, idem,
                "delivered", prior, None,
            )
            return True

        payload = delivery.get("payload") or {}
        if not isinstance(payload, dict):
            payload = {}
        try:
            screen = notification_detail_screen(payload)
            ref = None
            limit = int(getattr(getattr(self.transport, "capabilities", None), "max_text_chars", 4096))
            texts = chunks(screen.text, limit)
            for text in texts:
                part = screen if len(texts) == 1 else Screen(text)
                sent = self.transport.send_screen(subject, part)
                ref = str(sent.get("message_id")) if isinstance(sent, dict) and sent.get("message_id") is not None else ref
        except BotApiError as exc:
            self.backend.notification_receipt(
                self.platform, delivery_id, lease_token, idem,
                "retry" if exc.transient else "failed", None, exc.code,
            )
        except Exception:
            # Renderer/transport failures outside the provider error type must
            # still close the canonical lease with a safe, non-sensitive code.
            self.backend.notification_receipt(
                self.platform, delivery_id, lease_token, idem,
                "failed", None, "notification_delivery_failed",
            )
            return True
        # Remember the successful provider send before acknowledging the
        # canonical delivery. If the receipt call crashes, the next claim can
        # acknowledge it without sending the notification again.
        self.state.remember_delivery(delivery_id, ref or "sent")
        self.backend.notification_receipt(
            self.platform, delivery_id, lease_token, idem,
            "delivered", ref, None,
        )
        return True
