import json
import unittest
from unittest.mock import patch

from fanoos_bot.botapi import JsonBotApiTransport
from fanoos_bot.capabilities import TELEGRAM


class FakeResponse:
    def __enter__(self):
        return self

    def __exit__(self, *_args):
        return False

    def read(self):
        return json.dumps({"ok": True, "result": []}).encode("utf-8")


class BotApiLongPollTimeoutTest(unittest.TestCase):
    def test_socket_timeout_outlives_provider_long_poll(self):
        transport = JsonBotApiTransport(
            "https://example.invalid", "token", TELEGRAM, timeout=15
        )

        with patch(
            "fanoos_bot.botapi.request.urlopen", return_value=FakeResponse()
        ) as urlopen:
            self.assertEqual(transport.get_updates(0, 20), [])

        self.assertEqual(urlopen.call_args.kwargs["timeout"], 25.0)


if __name__ == "__main__":
    unittest.main()
