from __future__ import annotations

import importlib.util
import os
import shutil
import tempfile
import unittest
from pathlib import Path

_RUNTIME_PATH = Path(__file__).resolve().parents[2] / "apps" / "telegram-bot" / "runtime.py"


def _load_runtime():
    spec = importlib.util.spec_from_file_location("fanoos_telegram_runtime", _RUNTIME_PATH)
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(module)
    return module


class TelegramRuntimeBootTest(unittest.TestCase):
    """A missing forensic fingerprint key must disable leak investigation, not
    the bot. Requiring it at boot took the whole Telegram bot down in
    production (the key existed only in the protected-media worker's
    environment), which stopped every student reaching the bot for an owner
    capability none of them use.
    """

    def _environment(self, state_path: str) -> dict[str, str]:
        return {
            "FANOOS_API_ORIGIN": "https://example.invalid",
            "FANOOS_TELEGRAM_SERVICE_KEY_ID": "key-id",
            "FANOOS_TELEGRAM_SERVICE_SECRET": "0" * 48,
            "FANOOS_TELEGRAM_BOT_TOKEN": "123456:TEST-TOKEN",
            "FANOOS_TELEGRAM_STATE": state_path,
        }

    def test_boots_without_a_fingerprint_key_and_investigation_is_disabled(self) -> None:
        runtime = _load_runtime()
        work = tempfile.mkdtemp()
        # The bot opens its SQLite state and keeps the handle, so Windows will
        # not let the directory go; the contents are disposable either way.
        self.addCleanup(shutil.rmtree, work, True)
        environment = self._environment(str(Path(work) / "state.sqlite3"))
        original = dict(os.environ)
        os.environ.clear()
        os.environ.update(environment)
        os.environ.pop("FANOOS_PROTECTED_MEDIA_FINGERPRINT_KEY", None)
        try:
            _state, _backend, _transport, bot_runtime, _pump = runtime.build()
        finally:
            os.environ.clear()
            os.environ.update(original)
        self.assertEqual(bot_runtime.app.config.protected_media_fingerprint_key, b"")

    def test_a_present_fingerprint_key_is_carried_into_the_application(self) -> None:
        runtime = _load_runtime()
        work = tempfile.mkdtemp()
        self.addCleanup(shutil.rmtree, work, True)
        environment = self._environment(str(Path(work) / "state.sqlite3"))
        environment["FANOOS_PROTECTED_MEDIA_FINGERPRINT_KEY"] = "a" * 64
        original = dict(os.environ)
        os.environ.clear()
        os.environ.update(environment)
        try:
            _state, _backend, _transport, bot_runtime, _pump = runtime.build()
        finally:
            os.environ.clear()
            os.environ.update(original)
        self.assertEqual(bot_runtime.app.config.protected_media_fingerprint_key, b"a" * 64)


if __name__ == "__main__":
    unittest.main()
