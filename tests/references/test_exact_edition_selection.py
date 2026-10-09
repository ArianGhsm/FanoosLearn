"""Classification must skip absent exact official editions by default.

Synthetic test data only; no privately owned question or book content.
"""
import importlib.util
from pathlib import Path
import unittest

SCRIPT = Path(__file__).resolve().parents[2] / "scripts" / "references" / "classification_batch.py"
spec = importlib.util.spec_from_file_location("classification_batch", SCRIPT)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class OfficialEditionTests(unittest.TestCase):
    def setUp(self):
        self.texts = {
            "official@5e": {"missing": "not present", "nearest": "official@6e"},
            "official@6e": {"pdf": "safe-private-source.pdf"},
            "missing@1e": {"missing": "no complete book"},
        }

    def test_exact_available(self):
        self.assertEqual(module.searchable("official@6e", self.texts), "official@6e")

    def test_missing_is_pending_by_default(self):
        self.assertIsNone(module.searchable("official@5e", self.texts))
        self.assertIsNone(module.searchable("missing@1e", self.texts))

    def test_nearest_requires_explicit_legacy_opt_in(self):
        self.assertEqual(module.searchable("official@5e", self.texts, include_nearest=True),
                         "official@6e")
        self.assertIsNone(module.searchable("missing@1e", self.texts, include_nearest=True))


if __name__ == "__main__":
    unittest.main()
