from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from fanoos_bot.application import ApplicationConfig, BotApplication
from fanoos_bot.integrated_application import BotApplication as IntegratedBotApplication
from fanoos_bot.state import LocalState


class OwnerRecoveryBackend:
    """Backend fixture: management authority and the recovery token issuer
    vary independently, so a test can prove the bot re-checks authority on
    this specific action rather than trusting an earlier screen."""

    def __init__(self):
        self.subjects_that_can_manage: set[str] = set()
        self.issued_for: list[str] = []
        self.next_token = "recovery-token-abc"
        self.next_ttl = 600
        self.raise_on_request: Exception | None = None

    def deployment_overview(self, subject, target):
        return {"can_manage_deployments": subject in self.subjects_that_can_manage}

    def owner_recovery_request(self, platform, subject):
        if self.raise_on_request is not None:
            raise self.raise_on_request
        self.issued_for.append(subject)
        return {"token": self.next_token, "expires_in_seconds": self.next_ttl}


class _OwnerRecoveryMatrix:
    """Shared assertions, run against both the base class and the class the
    runtime actually constructs (apps/telegram-bot/runtime.py imports from
    integrated_application, not application) -- a base-class-only test would
    pass while proving nothing about the class that actually ships."""

    app_class: type

    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.path = Path(self.tmp.name) / "state.sqlite3"
        self.state = LocalState(self.path)
        self.backend = OwnerRecoveryBackend()
        self.app = self.app_class(
            self.backend,
            self.state,
            "telegram",
            ApplicationConfig("https://fanoos.test", "platform-primary"),
        )

    def tearDown(self):
        self.state.close()
        self.tmp.cleanup()

    @staticmethod
    def labels(screen):
        return [button.text for row in screen.rows for button in row]

    @staticmethod
    def urls(screen):
        return [button.url for row in screen.rows for button in row if button.url]

    def test_management_menu_offers_the_recovery_link_row(self):
        self.backend.subjects_that_can_manage.add("owner")
        result = self.app.management("owner", True)
        self.assertIn("🔑 پیوند ورود به وب", self.labels(result.screen))

    def test_owner_can_request_a_link(self):
        self.backend.subjects_that_can_manage.add("owner")
        result = self.app.owner_recovery_request("owner", True)
        self.assertEqual(["owner"], self.backend.issued_for)
        urls = self.urls(result.screen)
        self.assertEqual(1, len(urls))
        self.assertEqual("https://fanoos.test/recovery?token=recovery-token-abc", urls[0])

    def test_non_owner_is_refused_without_ever_requesting_a_token(self):
        result = self.app.owner_recovery_request("student", True)
        self.assertEqual([], self.backend.issued_for)
        self.assertEqual("owner_recovery", result.screen.presentation.semantic_kind)

    def test_group_chat_is_refused_before_checking_authority(self):
        self.backend.subjects_that_can_manage.add("owner")
        result = self.app.owner_recovery_request("owner", False)
        self.assertEqual([], self.backend.issued_for)
        self.assertIn("گفت‌وگوی خصوصی", result.screen.text)

    def test_backend_refusal_surfaces_without_crashing(self):
        from fanoos_bot.api import FanoosApiError

        self.backend.subjects_that_can_manage.add("owner")
        self.backend.raise_on_request = FanoosApiError("owner_recovery_rate_limited", "too many requests", 429)
        result = self.app.owner_recovery_request("owner", True)
        self.assertEqual([], self.urls(result.screen))


class BotOwnerRecoveryTest(_OwnerRecoveryMatrix, unittest.TestCase):
    app_class = BotApplication


class IntegratedBotOwnerRecoveryTest(_OwnerRecoveryMatrix, unittest.TestCase):
    app_class = IntegratedBotApplication


if __name__ == "__main__":
    unittest.main()
