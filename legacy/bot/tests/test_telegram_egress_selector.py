from __future__ import annotations

import base64
import importlib.util
from pathlib import Path
import unittest
from unittest.mock import patch
from urllib.error import URLError


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "telegram_egress_selector", ROOT / "scripts" / "select-xray-telegram-egress.py"
)
assert SPEC is not None and SPEC.loader is not None
SELECTOR = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(SELECTOR)


def _encoded(value: str) -> str:
    return base64.b64encode(value.encode("utf-8")).decode("ascii")


class _Response:
    def __init__(self, body: bytes) -> None:
        self.body = body

    def __enter__(self) -> "_Response":
        return self

    def __exit__(self, *_args: object) -> None:
        return None

    def read(self, _limit: int) -> bytes:
        return self.body


class TelegramEgressSelectorTests(unittest.TestCase):
    def test_multiple_subscription_sources_are_combined_and_deduplicated(self) -> None:
        first = base64.b64encode(b"vless://one\nvless://shared\n")
        second = base64.b64encode(b"vless://shared\nss://two\n")
        values = {
            "DENT_TELEGRAM_EGRESS_SUBSCRIPTION_URL_B64": _encoded("https://one.invalid/sub"),
            "DENT_TELEGRAM_EGRESS_SUBSCRIPTION_URL_B64_2": _encoded("https://two.invalid/sub"),
        }
        with patch.object(SELECTOR, "urlopen", side_effect=[_Response(first), _Response(second)]):
            links, fetched, failed = SELECTOR._fetch_subscription_links(values)
        self.assertEqual(links, ["vless://one", "vless://shared", "ss://two"])
        self.assertEqual((fetched, failed), (2, 0))

    def test_one_unreachable_subscription_does_not_discard_the_other(self) -> None:
        body = base64.b64encode(b"vless://working\n")
        values = {
            "DENT_TELEGRAM_EGRESS_SUBSCRIPTION_URL_B64": _encoded("https://down.invalid/sub"),
            "DENT_TELEGRAM_EGRESS_SUBSCRIPTION_URL_B64_2": _encoded("https://up.invalid/sub"),
        }
        with patch.object(SELECTOR, "urlopen", side_effect=[URLError("offline"), _Response(body)]):
            links, fetched, failed = SELECTOR._fetch_subscription_links(values)
        self.assertEqual(links, ["vless://working"])
        self.assertEqual((fetched, failed), (1, 1))


if __name__ == "__main__":
    unittest.main()
