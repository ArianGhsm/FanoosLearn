"""Regression guards for the complete, approved Carranza 14e PDF page map.

The production object SHA and boundaries were checked against the original
1876-page server PDF, without committing source material or a text derivative.
"""
import json
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
MAP = ROOT / "data/bank/reference-chapter-pages.json"
SHA = "32f50c3c01d6de459b0fdf84a79e969ed99844605d742d5571392555ce074327"


class Carranza14eMapTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.edition = json.loads(MAP.read_text(encoding="utf-8"))["editions"][
            "carranza-periodontology@14e"
        ]

    def test_exact_original_pdf_identity_and_full_page_coverage(self):
        self.assertEqual(self.edition["source_pdf_sha256"], SHA)
        runs = self.edition["runs"]
        self.assertEqual(runs[0], [None, 1, 26])
        self.assertEqual(runs[-1], [None, 1821, 1876])
        self.assertEqual(len(runs), 90)
        previous = 0
        for chapter, first, last in runs:
            self.assertEqual(first, previous + 1)
            self.assertGreaterEqual(last, first)
            previous = last
        self.assertEqual(previous, 1876)
        self.assertEqual([int(ch) for ch, _, _ in runs if ch is not None],
                         list(range(1, 89)))

    def test_book_chapter_headings_and_trailing_glossary_are_not_shifted(self):
        starts = {int(ch): first for ch, first, _ in self.edition["runs"]
                  if ch is not None}
        self.assertEqual(starts[1], 27)
        self.assertEqual(starts[3], 55)
        self.assertEqual(starts[4], 67)
        self.assertEqual(starts[25], 600)
        self.assertEqual(starts[32], 772)
        self.assertEqual(starts[79], 1623)
        self.assertEqual(starts[87], 1800)
        self.assertEqual(starts[88], 1811)
        self.assertIn(["88", 1811, 1820], self.edition["runs"])

    def test_three_exactly_matched_residency_paper_anchors_inside_chapters(self):
        for question, chapter, page in [(112, "5", 115),
                                        (118, "25", 606),
                                        (119, "25", 607)]:
            with self.subTest(question=question):
                matches = [ch for ch, start, end in self.edition["runs"]
                           if start <= page <= end]
                self.assertEqual(matches, [chapter])


if __name__ == "__main__":
    unittest.main()
