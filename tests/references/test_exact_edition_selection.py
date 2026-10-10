"""Classification must skip absent exact official editions by default.

Synthetic test data only; no privately owned question or book content.
"""
import importlib.util
from pathlib import Path
from tempfile import TemporaryDirectory
import unittest

SCRIPT = Path(__file__).resolve().parents[2] / "scripts" / "references" / "classification_batch.py"
spec = importlib.util.spec_from_file_location("classification_batch", SCRIPT)
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)


class OfficialEditionTests(unittest.TestCase):
    def setUp(self):
        self.pdfs = {
            "official@5e": {"missing": "not present", "nearest": "official@6e"},
            "official@6e": {"pdf": "verified-server-pdf"},
            "missing@1e": {"missing": "no complete book"},
        }

    def test_exact_available(self):
        self.assertEqual(module.pdf_available("official@6e", self.pdfs), "official@6e")

    def test_missing_is_pending_by_default(self):
        self.assertIsNone(module.pdf_available("official@5e", self.pdfs))
        self.assertIsNone(module.pdf_available("missing@1e", self.pdfs))

    def test_nearest_requires_explicit_legacy_opt_in(self):
        self.assertEqual(module.pdf_available("official@5e", self.pdfs, include_nearest=True),
                         "official@6e")
        self.assertIsNone(module.pdf_available("missing@1e", self.pdfs, include_nearest=True))

    def test_query_output_is_atomically_written_and_leaves_no_staging_file(self):
        with TemporaryDirectory() as tmp:
            output = Path(tmp) / "queries.json"
            module.save_query_file(output, [{"key": "synthetic", "editions": [], "terms": []}])
            self.assertIn('"synthetic"', output.read_text(encoding="utf-8"))
            self.assertEqual(list(Path(tmp).glob("*.tmp")), [])


if __name__ == "__main__":
    unittest.main()
