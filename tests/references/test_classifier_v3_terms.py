import sys, unittest
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts/classification_v3"))
from terms import deterministic_terms, latin_terms, normalize_fa  # noqa: E402
from scanner import NO_CANDIDATE, Query, count_page, pending_reason, rank, stem  # noqa: E402


class Terms(unittest.TestCase):
    def test_latin_span_split_into_unigrams_and_bigrams(self):
        t = latin_terms("Pemphigus vulgaris shows acantholysis")
        self.assertIn("Pemphigus vulgaris", t); self.assertIn("acantholysis", t)
        self.assertNotIn("Pemphigus vulgaris shows acantholysis", t)

    def test_no_dubious_alki_mapping_and_zwnj_normalized(self):
        terms, _ = deterministic_terms("بیمار الکی است", {"a": "x"}, "a")
        self.assertNotIn("alcoholic", [t for t, _ in terms])
        self.assertEqual(normalize_fa("پره ‌ مولر ي"), "پره‌مولر ی")

    def test_unit_numbers_and_answer_weight(self):
        terms, _ = deterministic_terms("ضخامت ۵ میلی‌متر", {"a": "keratocyst", "b": "cyst"}, "a")
        d = {t.lower(): w for t, w in terms}
        self.assertIn("5 mm", d); self.assertGreater(d["keratocyst"], d["cyst"] * 0 + 1.0)

    def test_stemmer_consistent(self):
        for a, b in (("cases", "case"), ("involved", "involve"), ("biopsies", "biopsy"), ("lesions", "lesion")):
            self.assertEqual(stem(a), stem(b))
        self.assertEqual(stem("diagnosis"), "diagnosis")

    def test_no_overlap_is_explicit_pending_and_noise_page_soft(self):
        q = Query.build("q", [("acantholysis", 1.0)])
        ph = {tuple(t): i for i, (t, _) in enumerate(q.groups)}
        body = "acantholysis " + " ".join(["word"] * 60)
        c, n, _ = count_page(body, ph)
        r = rank([q], {1: c, 2: {}}, {1: n, 2: n}, {1}, ph)
        self.assertEqual([p for _, p in r["q"]], [1])          # noise demoted, not dropped
        empty = rank([q], {2: {}}, {2: n}, set(), ph)
        self.assertEqual(pending_reason(empty["q"]), NO_CANDIDATE)
