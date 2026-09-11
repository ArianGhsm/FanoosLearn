from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from dent_bot.api import BotApiError
from dent_bot.keyboard_invariant import KEYBOARD_CLEANUP_VERSION, KeyboardInvariantApi
from dent_bot.state import BotState


class CapturingApi:
    def __init__(self) -> None:
        self.removed: list[int] = []
        self.sent: list[tuple[int, str, dict]] = []
        self.fail_remove = False

    def remove_reply_keyboard(self, chat_id: int):
        if self.fail_remove:
            raise BotApiError("cleanup unavailable", transient=True)
        self.removed.append(chat_id)
        return {"message_id": len(self.removed)}

    def send(self, chat_id: int, text: str, keyboard: dict):
        self.sent.append((chat_id, text, keyboard))
        return {"message_id": len(self.sent)}

    def edit(self, chat_id: int, message_id: int, text: str, keyboard: dict):
        return {"message_id": message_id}


class KeyboardInvariantTests(unittest.TestCase):
    def test_legacy_unknown_and_every_reply_to_inline_transition_are_cleaned_once(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            delegate = CapturingApi()
            try:
                api = KeyboardInvariantApi(delegate, state)
                inline = {"inline_keyboard": [[{"text": "خانه", "callback_data": "v1:home"}]]}
                reply = {"keyboard": [[{"text": "مرحله قبل"}]], "resize_keyboard": True}

                # An unknown record may be a keyboard left by an older release.
                api.send(20, "خانه", inline)
                self.assertEqual(delegate.removed, [20])
                api.send(20, "حساب", inline)
                self.assertEqual(delegate.removed, [20])

                api.send(20, "کد را وارد کن", reply)
                self.assertTrue(state.reply_keyboard_needs_removal(20, KEYBOARD_CLEANUP_VERSION))
                api.send(20, "تأیید شد", inline)
                self.assertEqual(delegate.removed, [20, 20])

                # The durable marker survives a process/app recreation.
                restarted = KeyboardInvariantApi(delegate, state)
                restarted.send(20, "منو", inline)
                self.assertEqual(delegate.removed, [20, 20])
            finally:
                state.close()

    def test_failed_cleanup_blocks_inline_transition_and_remains_retryable(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            delegate = CapturingApi()
            delegate.fail_remove = True
            try:
                api = KeyboardInvariantApi(delegate, state)
                with self.assertRaises(BotApiError):
                    api.send(20, "خانه", {"inline_keyboard": []})
                self.assertEqual(delegate.sent, [])
                self.assertTrue(state.reply_keyboard_needs_removal(20, KEYBOARD_CLEANUP_VERSION))
            finally:
                state.close()

    def test_explicit_cleanup_is_idempotent_after_authentication(self) -> None:
        with tempfile.TemporaryDirectory() as directory:
            state = BotState(Path(directory) / "state.sqlite3")
            delegate = CapturingApi()
            try:
                api = KeyboardInvariantApi(delegate, state)
                api.remove_reply_keyboard(20)
                api.remove_reply_keyboard(20)
                api.remove_reply_keyboard(20)
                self.assertEqual(delegate.removed, [20])

                # A real reply keyboard becoming active again legitimately
                # requires exactly one fresh cleanup, then becomes quiet again.
                api.send(20, "مرحله احراز هویت", {
                    "keyboard": [[{"text": "مرحله قبل"}]],
                    "resize_keyboard": True,
                })
                api.remove_reply_keyboard(20)
                api.remove_reply_keyboard(20)
                self.assertEqual(delegate.removed, [20, 20])
            finally:
                state.close()


if __name__ == "__main__":
    unittest.main()
