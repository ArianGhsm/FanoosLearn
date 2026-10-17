"""Pin the Shillingburg 4e chapter map to the current approved PDF."""
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
MAP = ROOT / "data/bank/reference-chapter-pages.json"
EDITION = "shillingburg-fixed@4e"
SHA256 = "f9c42a8d37993682c0b8ccd8283e75606bbd9c530663dd38e6aa79627ec97b2c"


class Shillingburg4ePageMapTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.edition = json.loads(MAP.read_text(encoding="utf-8"))["editions"][EDITION]

    def test_map_matches_exact_approved_pdf_and_covers_all_pages(self):
        self.assertEqual(self.edition["source"], "verified-server-pdf")
        self.assertEqual(self.edition["source_pdf_sha256"], SHA256)
        runs = self.edition["runs"]
        self.assertEqual(runs[0], [None, 1, 1])
        self.assertEqual(runs[1], ["1", 2, 23])
        self.assertEqual(runs[-1], ["29", 554, 585])
        previous_end = 0
        for _, start, end in runs:
            self.assertEqual(start, previous_end + 1)
            self.assertGreaterEqual(end, start)
            previous_end = end
        self.assertEqual(previous_end, 585)
        self.assertEqual(self.edition["chapters_seen"], 29)
        self.assertEqual(self.edition["chapters_listed"], 29)
        self.assertEqual(self.edition["not_seen"], [])

    def test_checked_chapter_boundaries_match_the_approved_pdf(self):
        starts = {chapter: start for chapter, start, _ in self.edition["runs"]}
        self.assertEqual(starts["1"], 2)
        self.assertEqual(starts["13"], 214)
        self.assertEqual(starts["14"], 240)
        self.assertEqual(starts["29"], 554)


if __name__ == "__main__":
    unittest.main()
