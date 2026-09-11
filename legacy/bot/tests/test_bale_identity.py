from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from dent_bot.api import BOT_COMMANDS
from dent_bot.app import DentBotApp
from dent_bot.state import BotState
from dent_bot.ui import (
    account_screen,
    identity_mapping_remove_screen,
    owner_identity_mappings_screen,
)


class FakeApi:
    def __init__(self) -> None:
        self.sent: list[tuple] = []

    def send(self, chat_id, text, keyboard):
        self.sent.append((chat_id, text, keyboard))
        return {"message_id": 1}


class BaleAccountSite:
    def __init__(self) -> None:
        self.account_ids: list[int] = []

    def account(self, user_id: int) -> dict:
        self.account_ids.append(user_id)
        return {"success": True, "linked": False, "identity": {}}


class BaleIdentityTests(unittest.TestCase):
    def test_verify_command_is_shared_by_both_adapters(self) -> None:
        self.assertIn(
            {"command": "verify", "description": "احراز هویت و اتصال حساب"},
            BOT_COMMANDS,
        )

    def test_bale_verify_command_cannot_bypass_the_shared_entry_gateway(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                api = FakeApi()
                site = BaleAccountSite()
                app = DentBotApp(
                    api,
                    state,
                    owner_id=10,
                    site_url="https://example.test",
                    site_api=site,
                    platform="bale",
                )
                app.handle({
                    "message": {
                        "message_id": 1,
                        "text": "/verify",
                        "chat": {"id": 20, "type": "private"},
                        "from": {"id": 20, "first_name": "دانشجو"},
                    }
                })
                self.assertEqual(site.account_ids, [20])
                self.assertIn("خوش آمدی به دنت‌یار", api.sent[0][1])
                self.assertNotIn("v1:link-account", str(api.sent[0][2]))
            finally:
                state.close()

    def test_bale_identity_copy_never_calls_it_telegram(self) -> None:
        screens = [
            account_screen("https://example.test", platform="bale"),
            owner_identity_mappings_screen(
                {"mappings": [{"name": "دانشجو", "studentNumber": "402000", "platformUserId": "123"}]},
                platform="bale",
            ),
            identity_mapping_remove_screen(
                {"name": "دانشجو", "studentNumber": "402000", "platformUserId": "123"}, platform="bale"
            ),
        ]
        for screen in screens:
            with self.subTest(text=screen.text[:30]):
                self.assertIn("بله", screen.text + str(screen.keyboard))
                self.assertNotIn("تلگرام", screen.text + str(screen.keyboard))

    def test_owner_mapping_screen_is_read_delete_only(self) -> None:
        screen = owner_identity_mappings_screen(
            {"mappings": [{"ref": "abcdefghijkl", "name": "دانشجو", "studentNumber": "402000", "platformUserId": "123"}]},
            platform="bale",
        )
        rendered = screen.text + str(screen.keyboard)
        self.assertIn("اتصال دستی غیرفعال", rendered)
        self.assertNotIn("identity-mapping-new", rendered)
        self.assertNotIn("identity-claims", rendered)


if __name__ == "__main__":
    unittest.main()
