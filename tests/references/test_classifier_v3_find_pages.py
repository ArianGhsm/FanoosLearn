import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts/classification_v3"))
sys.path.insert(0, str(ROOT / "scripts/references"))

import find_pages as F  # noqa: E402

FILLER = " ".join(["alpha", "beta", "gamma", "delta", "epsilon"] * 12)
PAGES = {
    10: "Front matter " + FILLER,
    20: FILLER + " The facemask produces maxillary protraction in young Class III patients. " + FILLER,
    21: FILLER + " Headgear is discussed here briefly. " + FILLER,
    30: FILLER + " The facemask and maxillary protraction are outside this chapter. " + FILLER,
}
RUNS = [[None, 1, 15], ["1", 16, 25], ["2", 26, 40]]


class FakePdf:
    source_sha256 = "a" * 64
    page_count = 40

    def __init__(self):
        self.reads = []

    def page_text(self, p):
        self.reads.append(p)
        return PAGES.get(p, FILLER)


class Scope(unittest.TestCase):
    def test_allowed_pages_respects_listed_chapters_and_skips_front_matter(self):
        self.assertEqual(F.allowed_pages(RUNS, ["1"]), set(range(16, 26)))
        self.assertEqual(F.allowed_pages(RUNS, None), set(range(16, 41)))
        self.assertEqual(F.allowed_pages(RUNS, []), set())

    def test_build_queries_caps_terms_and_clamps_weights(self):
        (q,) = F.build_queries({"q1": ["facemask", ["maxillary protraction", 99], 5, "", ["x"]]})
        self.assertEqual(len(q.groups), 2)
        self.assertEqual(q.groups[1][1], 5.0)
        with self.assertRaises(ValueError):
            F.build_queries({"q1": "facemask"})


class Search(unittest.TestCase):
    def test_in_scope_only_ranking_and_never_reads_out_of_scope_pages(self):
        pdf = FakePdf()
        res = F.search(pdf, RUNS, {"q1": ["1"]}, {"q1": ["facemask", "maxillary protraction"]}, "ed@1", top=3)
        top = res["results"]["q1"][0]
        self.assertEqual((top["page"], top["chapter"], top["in_scope"]), (20, "1", True))
        self.assertIn("facemask", top["window_not_exact"])
        self.assertTrue(all(16 <= p <= 25 for p in pdf.reads))
        self.assertFalse(res["partial"])
        self.assertEqual(res["pdf_sha256"], "a" * 64)

    def test_all_chapters_marks_out_of_scope_hits(self):
        res = F.search(FakePdf(), RUNS, {"q1": ["1"]}, {"q1": ["facemask", "maxillary protraction"]},
                       "ed@1", top=3, all_chapters=True)
        flags = {r["page"]: r["in_scope"] for r in res["results"]["q1"]}
        self.assertEqual(flags, {20: True, 30: False})

    def test_unknown_question_ids_and_empty_terms(self):
        self.assertIn("error", F.search(FakePdf(), RUNS, {"q1": None}, {"other": ["facemask"]}, "ed@1"))
        res = F.search(FakePdf(), RUNS, {"q1": None}, {"q1": ["zebra"]}, "ed@1")
        self.assertIsInstance(res["results"]["q1"], str)


class Quote(unittest.TestCase):
    PAGE = "The facemask produces maxillary protraction in young Class III patients."

    def test_exact_four_word_quote_passes(self):
        self.assertIsNone(F.quote_issue(self.PAGE, "The facemask produces maxillary protraction"))

    def test_short_fragment_inexact_case_and_long_quotes_fail(self):
        self.assertEqual(F.quote_issue(self.PAGE, "facemask produces maxillary"), "each_fragment_needs_at_least_4_words")
        self.assertEqual(F.quote_issue(self.PAGE, "the Facemask Produces maxillary protraction"),
                         "not_exact_case_punctuation_match_on_page")
        self.assertEqual(F.quote_issue(self.PAGE, "x " * 200), "quote_longer_than_300_chars")
        self.assertEqual(F.quote_issue(self.PAGE, ""), "empty_quote")

    def test_check_draft_reports_only_proposed_rows(self):
        with tempfile.TemporaryDirectory() as d:
            Path(d, "decisions.draft.json").write_text(json.dumps({"decisions": [
                {"question_id": "a", "decision": "proposed", "edition": "ed@1", "pdf_page": 20,
                 "evidence_quote": "The facemask produces maxillary protraction"},
                {"question_id": "b", "decision": "proposed", "edition": "ed@1", "pdf_page": 20,
                 "quote": "maxillary protraction only"},
                {"question_id": "c", "decision": "pending", "reason": "x"}]}))
            res = F.check_draft(Path(d), lambda ed: FakePdf())
            self.assertEqual((res["proposed"], res["ok"]), (2, 1))
            self.assertEqual(res["failing"][0]["question_id"], "b")


if __name__ == "__main__":
    unittest.main()
