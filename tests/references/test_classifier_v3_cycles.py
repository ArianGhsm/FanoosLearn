"""Regression tests for the 2026-11-09 improvement cycles (accepted bridge, no auto-accept)."""
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts/classification_v3"))
import pipeline as P  # noqa: E402
from terms import c1_terms, deterministic_terms  # noqa: E402


class BridgeTests(unittest.TestCase):
    def test_persian_only_stem_gets_terms(self):
        base, _ = deterministic_terms("کدام ضایعه نمای سینوزوئیدال دارد؟", {"a": "همانژیوم", "b": "کارسینوم"}, "a")
        c1, _ = c1_terms("کدام ضایعه نمای سینوزوئیدال دارد؟", {"a": "همانژیوم", "b": "کارسینوم"}, "a")
        self.assertEqual(base, [])
        got = dict(c1)
        self.assertGreater(got["hemangioma"], got["carcinoma"])   # official key outweighs distractor
        self.assertIn("sinusoidal", got)

    def test_short_keys_are_whole_words(self):
        terms = dict(c1_terms("کدام سلول دیده می‌شود", {"a": "x"}, "a")[0])
        self.assertNotIn("tuberculosis", terms)   # 'سل' inside 'سلول'
        self.assertNotIn("palate", terms)         # 'کام' inside 'کدام'

    def test_import_letter_doubling_still_bridges(self):
        self.assertIn("epithelial", dict(c1_terms("سلول اپیتلییال", {"a": "x"}, "a")[0]))

    def test_generic_words_downweighted(self):
        terms = dict(c1_terms("نمای میکروسکوپی آملوبلاستوما", {"a": "x"}, "a")[0])
        self.assertLess(terms["microscopic"], terms["ameloblastoma"])

    def test_voided_or_missing_key_keeps_choices_unweighted(self):
        terms = dict(c1_terms("کدام", {"a": "همانژیوم", "b": "کارسینوم"}, None)[0])
        self.assertEqual(terms["hemangioma"], terms["carcinoma"])


class NoAutoAcceptTests(unittest.TestCase):
    def test_top1_candidate_is_never_accepted_or_packaged(self):
        d = P.Decision(question_id="x", edition="b@1e", pdf_sha256="a" * 64, pdf_page=3, node_id="ch1",
                       quote="one two three four five six", confidence=0.99, scope="in_scope",
                       basis="official_exam_year", producer="v3")
        self.assertEqual(P.precheck([]), "audit_pending")
        with tempfile.TemporaryDirectory() as root, self.assertRaises(ValueError):
            P.handoff_package([(d, {})], "b" * 64, "b1", Path(root))


if __name__ == "__main__":
    unittest.main()
