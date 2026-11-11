"""Regression: absent source rows must be sampled via official validity."""
import base64
import sys
import unittest
from pathlib import Path
from unittest.mock import patch

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts" / "classification_v3"))
import benchmark


class MissingSampleTests(unittest.TestCase):
    def test_missing_without_source_row_is_selected(self):
        calls = []
        qid = "11111111-1111-1111-1111-111111111111"
        stem = base64.b64encode("Question concept".encode()).decode()
        opt = base64.b64encode("Correct option".encode()).decode()

        def fake_sql(query):
            calls.append(query)
            if "FROM bank_questions q" in query:
                return [[qid, stem, "1400", "1", "1", "final"]]
            if "FROM bank_question_choices" in query:
                return [["1", opt]]
            raise AssertionError("unexpected query")

        with patch.object(benchmark, "sql", side_effect=fake_sql):
            result = benchmark.sample_missing_officially_eligible(
                "neville-oral-pathology@5e", "board", 2
            )
        self.assertEqual(len(result), 1)
        self.assertEqual(result[0]["key"], "a")
        self.assertEqual(result[0]["stem"], "Question concept")
        self.assertIn("bank_reference_validity", calls[0])
        self.assertIn("rv.is_official=1", calls[0])
        self.assertIn("NOT EXISTS", calls[0])
        self.assertNotIn("FROM bank_question_sources s JOIN", calls[0])

    def test_rejects_invalid_edition(self):
        with self.assertRaises(ValueError):
            benchmark.sample_missing_officially_eligible(
                "bad';DROP TABLE bank_questions;--@5e", "board", 1
            )


if __name__ == "__main__":
    unittest.main()
