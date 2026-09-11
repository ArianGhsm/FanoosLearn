from __future__ import annotations

import json
import os
import tempfile
import unittest
from pathlib import Path

from deploy_notifier.core import DeployEvent, DeliveryResult, Notifier, Spool
from deploy_notifier.config import configured_channel_health, load_settings
from deploy_notifier.transports import BotApiTransport, SiteNotificationTransport, validate_loopback_http_proxy


class FakeTransport:
    def __init__(self, name: str, results: list[DeliveryResult]) -> None:
        self.name = name
        self.results = results
        self.messages: list[str] = []

    def send(self, event: DeployEvent) -> DeliveryResult:
        self.messages.append(event.message(html_format=self.name == "telegram", platform=self.name))
        return self.results.pop(0) if self.results else DeliveryResult(True)


class CapturingBotApiTransport(BotApiTransport):
    def __init__(self, **kwargs) -> None:
        super().__init__(**kwargs)
        self.fields: list[dict[str, object]] = []

    def _send_fields(self, fields: dict[str, object]) -> DeliveryResult:
        self.fields.append(fields)
        return DeliveryResult(True)


class FakeSiteClient:
    def __init__(self) -> None:
        self.requests: list[tuple[str, int, dict[str, object]]] = []

    def request(self, action: str, owner_id: int, **fields) -> dict:
        self.requests.append((action, owner_id, fields))
        return {"success": True}


class DeployNotifierTests(unittest.TestCase):
    def test_html_fallback_removes_markup_safely(self) -> None:
        rendered = BotApiTransport._plain_text("<b>موفق</b> <code>v&lt;1&gt;</code>")
        self.assertEqual(rendered, "موفق v<1>")

    def test_placeholder_credentials_are_not_ready(self) -> None:
        values = {
            "DENT_DEPLOY_NOTIFY_CHANNELS": "telegram,bale",
            "DENT_DEPLOY_TELEGRAM_BOT_TOKEN": "<telegram-bot-token>",
            "DENT_DEPLOY_TELEGRAM_CHAT_ID": "<telegram-owner-chat-id>",
            "DENT_DEPLOY_BALE_BOT_TOKEN": "<bale-bot-token>",
            "DENT_DEPLOY_BALE_CHAT_ID": "<bale-owner-chat-id>",
        }
        previous = {key: os.environ.get(key) for key in values}
        try:
            os.environ.update(values)
            self.assertEqual(configured_channel_health(load_settings()), {"telegram": False, "bale": False})
        finally:
            for key, value in previous.items():
                if value is None:
                    os.environ.pop(key, None)
                else:
                    os.environ[key] = value

    def test_message_contains_bounded_deploy_context(self) -> None:
        event = DeployEvent.create(
            service="website",
            status="succeeded",
            version="release-42",
            summary="health checks passed",
            actor="deploy-script",
            event_id="site-release-42-succeeded",
        )
        message = event.message()
        self.assertIn("استقرار موفق", message)
        self.assertIn("وب‌سایت دندان‌پزشکی ۱۴۰۲", message)
        self.assertIn("release-42", message)
        self.assertIn("با موفقیت منتشر شد", message)

    def test_persian_emoji_and_zwnj_survive_event_spool_and_message(self) -> None:
        text = "اعلان فارسیِ دنت‌یار بدون تبدیل به علامت سؤال ✅"
        with tempfile.TemporaryDirectory() as directory:
            spool = Spool(Path(directory))
            transport = FakeTransport("telegram", [DeliveryResult(True)])
            event = DeployEvent.create(
                service="website",
                status="succeeded",
                summary=text,
                event_id="utf8-round-trip",
            )
            Notifier(spool, {"telegram": transport}).publish(event, ("telegram",))
            stored = (spool.delivered / "utf8-round-trip.json").read_bytes()
            self.assertIn(text.encode("utf-8"), stored)
            self.assertIn(text, transport.messages[0])
            self.assertNotIn("?" * 4, stored.decode("utf-8"))

    def test_windows_emitter_uses_ascii_safe_base64_transport(self) -> None:
        emitter = (Path(__file__).resolve().parents[1] / "scripts/emit-deploy-status.ps1").read_text(encoding="utf-8")
        self.assertIn("UTF8Encoding", emitter)
        self.assertIn("ToBase64String", emitter)
        self.assertIn("base64 --decode", emitter)
        self.assertIn("& scp", emitter)
        self.assertIn(".incoming-", emitter)
        self.assertIn("$remoteUploaded", emitter)
        self.assertIn("sudo rm -f", emitter)
        self.assertIn("runuser -u dentops --preserve-environment", emitter)
        self.assertNotIn("$payload | & ssh", emitter)
        self.assertNotIn("$payloadBase64 | & ssh", emitter)

    def test_telegram_message_is_safe_polished_html(self) -> None:
        event = DeployEvent.create(
            service="telegram-bot",
            status="failed",
            version="v1<unsafe>",
            summary="processor <failed> & retry stopped",
            event_id="telegram-v1-failed",
        )
        message = event.message(html_format=True, platform="telegram")
        self.assertIn("<b>🔴 استقرار ناموفق</b>", message)
        self.assertIn("دنت‌یار Telegram", message)
        self.assertIn("<blockquote>", message)
        self.assertIn("v1&lt;unsafe&gt;", message)
        self.assertNotIn("<unsafe>", message)
        self.assertNotIn("processor <failed>", message)
        self.assertNotIn("<tg-time", message)
        self.assertIn("ساعت ", message)
        self.assertIn(" ۱۴۰۵/", message)

    def test_bale_message_uses_safe_text_date_fallback(self) -> None:
        event = DeployEvent.create(service="bale-bot", status="started", event_id="bale-date")
        message = event.message(html_format=True, platform="bale")
        self.assertNotIn("<tg-time", message)
        self.assertIn("ساعت", message)

    def test_bale_plain_fallback_has_no_html_markup(self) -> None:
        event = DeployEvent.create(service="bale-bot", status="started", event_id="bale-started")
        message = event.message()
        self.assertIn("🔵 استقرار آغاز شد", message)
        self.assertIn("دنت‌یار بله", message)
        self.assertNotIn("<b>", message)

    def test_bale_transport_converts_html_to_native_markdown_without_parse_mode(self) -> None:
        transport = CapturingBotApiTransport(
            name="bale",
            api_root="https://tapi.bale.ai",
            token="token",
            chat_id="1",
            timeout=2,
            bale_markdown=True,
        )
        event = DeployEvent.create(service="bale-bot", status="succeeded", event_id="bale-rich")
        self.assertTrue(transport.send(event).ok)
        fields = transport.fields[0]
        self.assertNotIn("parse_mode", fields)
        self.assertNotIn("<b>", str(fields["text"]))
        self.assertIn("*🟢 استقرار موفق*", str(fields["text"]))

    def test_telegram_proxy_is_loopback_only(self) -> None:
        self.assertEqual(validate_loopback_http_proxy("http://127.0.0.1:11080"), "http://127.0.0.1:11080")
        with self.assertRaises(ValueError):
            validate_loopback_http_proxy("http://10.0.0.8:11080")
        with self.assertRaises(ValueError):
            validate_loopback_http_proxy("http://user:pass@127.0.0.1:11080")

    def test_site_transport_sends_structured_idempotent_event(self) -> None:
        client = FakeSiteClient()
        transport = SiteNotificationTransport(client=client, owner_id=42)  # type: ignore[arg-type]
        event = DeployEvent.create(service="website", status="started", event_id="site-v1-started")
        self.assertTrue(transport.send(event).ok)
        action, owner_id, fields = client.requests[0]
        self.assertEqual(action, "createDeployNotification")
        self.assertEqual(owner_id, 42)
        self.assertEqual(fields["event"]["event_id"], "site-v1-started")

    def test_successful_delivery_moves_event_to_delivered(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            spool = Spool(Path(directory))
            telegram = FakeTransport("telegram", [DeliveryResult(True)])
            bale = FakeTransport("bale", [DeliveryResult(True)])
            site = FakeTransport("site", [DeliveryResult(True)])
            notifier = Notifier(spool, {"telegram": telegram, "bale": bale, "site": site})
            event = DeployEvent.create(service="archive-worker", status="started", event_id="event-1")
            payload = notifier.publish(event, ("telegram", "bale", "site"))

            self.assertFalse((spool.pending / "event-1.json").exists())
            self.assertTrue((spool.delivered / "event-1.json").exists())
            self.assertEqual(payload["deliveries"]["telegram"]["status"], "delivered")
            self.assertEqual(len(telegram.messages), 1)
            self.assertEqual(len(bale.messages), 1)
            self.assertEqual(payload["deliveries"]["site"]["status"], "delivered")
            self.assertEqual(len(site.messages), 1)

    def test_missing_channel_is_durably_pending(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            spool = Spool(Path(directory))
            notifier = Notifier(spool, {})
            event = DeployEvent.create(service="telegram-bot", status="failed", event_id="event-2")
            payload = notifier.publish(event, ("telegram",))

            self.assertTrue((spool.pending / "event-2.json").exists())
            state = payload["deliveries"]["telegram"]
            self.assertEqual(state["status"], "pending")
            self.assertEqual(state["last_error"], "channel-not-configured")

    def test_event_id_is_idempotent(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            spool = Spool(Path(directory))
            notifier = Notifier(spool, {})
            event = DeployEvent.create(service="website", status="started", event_id="same-event")
            notifier.publish(event, ("telegram",))
            notifier.publish(event, ("telegram",))
            self.assertEqual(len(list(spool.pending.glob("*.json"))), 1)

    def test_unreachable_channel_can_be_deferred_without_deleting_evidence(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            spool = Spool(Path(directory))
            event = DeployEvent.create(service="bale-bot", status="failed", event_id="bale-blocked")
            Notifier(spool, {}).publish(event, ("bale",))

            payload = spool.defer("bale-blocked", "platform-network-unreachable")

            self.assertFalse((spool.pending / "bale-blocked.json").exists())
            self.assertTrue((spool.deferred / "bale-blocked.json").exists())
            self.assertEqual(payload["deferred_reason"], "platform-network-unreachable")
            self.assertNotIn("token", json.dumps(payload).lower())

    def test_spool_never_contains_transport_secrets(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            spool = Spool(Path(directory))
            notifier = Notifier(spool, {})
            event = DeployEvent.create(service="website", status="started", event_id="event-3")
            notifier.publish(event, ("telegram", "bale"))
            serialized = (spool.pending / "event-3.json").read_text(encoding="utf-8")
            parsed = json.loads(serialized)
            self.assertNotIn("token", serialized.lower())
            self.assertNotIn("chat_id", serialized.lower())
            self.assertEqual(parsed["event"]["event_id"], "event-3")


if __name__ == "__main__":
    unittest.main()
