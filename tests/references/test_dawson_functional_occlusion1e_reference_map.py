"""Pin Dawson 1e chapters to the exact approved PDF's chapter-opening pages."""
import json
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
BANK = ROOT / "data" / "bank"
EDITION = "dawson-functional-occlusion@1e"
SHA256 = "d73477ea9a35ce32efad59e7e2af97c8b6eefb2a21c5dd4636c1d8315383b779"
STARTS = [11, 19, 25, 35, 41, 53, 65, 77, 83, 93, 99, 111, 121, 139, 149, 157,
          167, 185, 197, 207, 215, 241, 267, 273, 285, 315, 329, 341, 353, 357,
          373, 387, 401, 427, 437, 461, 475, 487, 501, 509, 521, 533, 555, 573,
          585, 589, 603]


class DawsonFunctionalOcclusion1eReferenceMapTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.maps = json.loads((BANK / "reference-chapter-pages.json").read_text(encoding="utf-8"))
        cls.tocs = json.loads((BANK / "reference-tocs.json").read_text(encoding="utf-8"))
        cls.pdfs = json.loads((BANK / "reference-pdfs.json").read_text(encoding="utf-8"))
        cls.catalog = json.loads((BANK / "catalog.json").read_text(encoding="utf-8"))
        cls.entry = cls.maps["editions"][EDITION]

    def test_map_matches_exact_approved_pdf_and_covers_every_page(self):
        entry = self.entry
        self.assertEqual(entry["source"], "verified-server-pdf")
        self.assertEqual(entry["source_pdf_sha256"], SHA256)
        self.assertEqual(entry["pdf_pages"], 638)
        self.assertEqual(entry["readable_pages"], 637)
        self.assertEqual(entry["chapters_seen"], 47)
        self.assertEqual(entry["chapters_listed"], 47)
        self.assertEqual(entry["not_seen"], [])

        runs = entry["runs"]
        self.assertEqual(runs[0], [None, 1, 10])
        self.assertEqual(runs[-2], ["47", 603, 610])
        self.assertEqual(runs[-1], [None, 611, 638])
        previous_end = 0
        chapter_starts = {}
        for chapter, start, end in runs:
            self.assertEqual(start, previous_end + 1)
            self.assertGreaterEqual(end, start)
            if chapter is not None:
                chapter_starts[chapter] = start
            previous_end = end
        self.assertEqual(previous_end, 638)
        self.assertEqual([chapter_starts[str(i)] for i in range(1, 48)], STARTS)

    def test_reference_chapters_and_documented_pdf_starts_match(self):
        pdf = self.pdfs["editions"][EDITION]
        self.assertEqual(pdf["pdf"], "verified-server-pdf")
        self.assertEqual(pdf["chapter_pdf_starts"], {str(i): page for i, page in enumerate(STARTS, 1)})
        self.assertEqual(pdf["backmatter_pdf_start"], 611)
        toc = self.tocs["editions"][EDITION]
        self.assertEqual([row[0] for row in toc["chapters"]], [str(i) for i in range(1, 48)])
        ref = next(r for r in self.catalog["references"] if r["key"] == "dawson-functional-occlusion")
        edition = next(e for e in ref["editions"] if e["key"] == "1e")
        self.assertEqual([n["number"] for n in edition["nodes"]], [str(i) for i in range(1, 48)])


if __name__ == "__main__":
    unittest.main()
