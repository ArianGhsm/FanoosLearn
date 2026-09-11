#!/usr/bin/env python3
"""No-network auth-gate smoke for an installed shared bot release."""

from __future__ import annotations

import tempfile
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from dent_bot.app import DentBotApp
from dent_bot.onboarding import START_GENERIC
from dent_bot.state import BotState


class ProbeApi:
    def __init__(self, *, channel_member: bool = True) -> None:
        self.sent: list[tuple] = []
        self.edited: list[tuple] = []
        self.answered: list[tuple] = []
        self.channel_member = channel_member
        self.membership_checks: list[tuple[str, int]] = []

    def send(self, *args):
        self.sent.append(args)
        return {"message_id": 1}

    def edit(self, *args):
        self.edited.append(args)
        return {"message_id": args[1]}

    def answer_callback(self, *args, **kwargs):
        self.answered.append((*args, kwargs))
        return True

    def is_chat_member(self, chat_id: str, user_id: int) -> bool:
        self.membership_checks.append((chat_id, user_id))
        return self.channel_member


class ProbeSite:
    def __init__(self, mode: str) -> None:
        self.mode = mode
        self.grade_calls = 0

    def account(self, _user_id: int) -> dict:
        if self.mode == "linked":
            return {"linked": True, "authComplete": True, "user": {}, "onboardingProfile": {}}
        if self.mode == "legacy-linked":
            return {
                "linked": True,
                "authComplete": False,
                "user": {},
                "onboardingProfile": {
                    "verifiedAt": "2026-08-27T20:00:00+03:30",
                    "isClassMember": True,
                },
            }
        if self.mode == "generic":
            return {
                "linked": False,
                "identity": {},
                "onboardingProfile": {
                    "firstName": "probe",
                    "verifiedAt": "2026-08-27T20:00:00+03:30",
                    "isClassMember": False,
                },
            }
        if self.mode == "class":
            return {
                "linked": False,
                "identity": {},
                "onboardingProfile": {
                    "verifiedAt": "2026-08-27T20:00:00+03:30",
                    "isClassMember": True,
                },
            }
        return {"linked": False, "identity": {}, "onboardingProfile": None}

    def grades(self, _user_id: int) -> dict:
        self.grade_calls += 1
        raise AssertionError("private site method was called without a canonical link")


class ResumeSite(ProbeSite):
    def __init__(self) -> None:
        super().__init__("unknown")

    def onboarding_catalog(self, _user_id: int) -> dict:
        return {
            "contractVersion": "bot-onboarding-v1",
            "majors": ["دندانپزشکی", "پزشکی", "داروسازی"],
            "entryYears": ["۱۳۹۹", "۱۴۰۰", "۱۴۰۱", "۱۴۰۲", "۱۴۰۳", "۱۴۰۴", "۱۴۰۵"],
            "entryTerms": ["نیمسال اول", "نیمسال دوم"],
            "courseTypes": ["روزانه یا تعهدی", "شهریه پرداز", "بین الملل"],
            "provinces": ["تهران"],
            "institutions": [
                {"province": "تهران", "name": "دانشگاه علوم پزشکی تهران", "system": "public"},
            ],
        }


def message(user_id: int, text: str) -> dict:
    return {
        "message": {
            "text": text,
            "from": {"id": user_id},
            "chat": {"id": user_id, "type": "private"},
        }
    }


def main() -> int:
    for platform in ("telegram", "bale"):
        for mode in ("unknown", "generic", "class", "legacy-linked"):
            with tempfile.TemporaryDirectory() as directory:
                api = ProbeApi()
                site = ProbeSite(mode)
                state = BotState(Path(directory) / "state.sqlite3")
                try:
                    app = DentBotApp(
                        api, state, owner_id=10, site_url="https://example.test",
                        site_api=site, platform=platform,
                    )
                    app.handle(message(20, "/menu"))
                    markup = api.sent[-1][2]
                    if mode == "unknown":
                        assert "keyboard" in markup and len(markup["keyboard"]) == 2
                    elif mode == "generic":
                        assert "inline_keyboard" in markup
                        app.handle({
                            "callback_query": {
                                "id": "generic-grades",
                                "from": {"id": 20},
                                "data": "v1:grades",
                                "message": {"message_id": 1, "chat": {"id": 20, "type": "private"}},
                            }
                        })
                        assert site.grade_calls == 0
                        assert "v1:link-account" in str(api.edited[-1][3])
                    else:
                        assert "keyboard" in markup and len(markup["keyboard"]) == 3
                        assert state.dialog(20)["kind"] == "class-auth-v1"
                        app.handle(message(20, markup["keyboard"][0][0]["text"]))
                        assert state.dialog(20)["step"] == "student-number"
                finally:
                    state.close()

        with tempfile.TemporaryDirectory() as directory:
            api = ProbeApi()
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                app = DentBotApp(
                    api, state, owner_id=10, site_url="https://example.test",
                    site_api=ProbeSite("linked"), platform=platform,
                )
                app.handle(message(10, "/product"))
                assert state.dialog(10)["kind"] == "payment-offer"
            finally:
                state.close()

        with tempfile.TemporaryDirectory() as directory:
            api = ProbeApi()
            state = BotState(Path(directory) / "state.sqlite3")
            try:
                app = DentBotApp(
                    api, state, owner_id=10, site_url="https://example.test",
                    site_api=ResumeSite(), platform=platform,
                )
                app.handle(message(20, START_GENERIC))
                app.handle(message(20, "آرین"))
                before = state.dialog(20)
                app.handle(message(20, "/start"))
                assert state.dialog(20) == before
                assert before["step"] == "last-name"
            finally:
                state.close()

    with tempfile.TemporaryDirectory() as directory:
        api = ProbeApi(channel_member=False)
        site = ProbeSite("linked")
        state = BotState(Path(directory) / "state.sqlite3")
        try:
            app = DentBotApp(
                api, state, owner_id=10, site_url="https://example.test",
                site_api=site, platform="telegram", bot_username="Dent1402Bot",
                required_channel_username="Dent1402Booklets",
            )
            app.handle(message(20, "/menu"))
            assert api.membership_checks == [("@Dent1402Booklets", 20)]
            assert "Dent1402Booklets" in str(api.sent[-1][2])
            assert site.grade_calls == 0

            app.handle({
                "callback_query": {
                    "id": "membership-still-blocked",
                    "from": {"id": 20},
                    "data": "v1:membership-check",
                    "message": {"message_id": 1, "chat": {"id": 20, "type": "private"}},
                }
            })
            assert api.answered[-1][-1].get("show_alert") is True

            api.channel_member = True
            app.handle({
                "callback_query": {
                    "id": "membership-check",
                    "from": {"id": 20},
                    "data": "v1:membership-check",
                    "message": {"message_id": 1, "chat": {"id": 20, "type": "private"}},
                }
            })
            assert api.membership_checks[-1] == ("@Dent1402Booklets", 20)
            assert "v1:home" not in str(api.edited[-1][3])
            assert "عضویت تأیید شد" in str(api.answered[-1])
        finally:
            state.close()

    print("DEPLOYED_AUTH_MATRIX_OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
